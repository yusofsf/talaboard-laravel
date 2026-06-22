<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = $request->user()->tickets()->withCount('messages')
            ->orderByDesc('updated_at')->get()
            ->map(fn ($t) => [
                'id'        => $t->id,
                'subject'   => $t->subject,
                'status'    => $t->status,
                'msg_count' => $t->messages_count,
                'created_at' => Jalali::format($t->created_at),
            ]);

        return Inertia::render('Tickets/Index', ['tickets' => $tickets]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
        ]);

        $user = $request->user();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => $request->subject, 'status' => 'open']);
        TicketMessage::create([
            'ticket_id' => $ticket->id, 'user_id' => $user->id, 'is_admin' => false,
            'message' => $request->message, 'created_at' => now(),
        ]);

        $this->notifyAllAdmins("تیکت جدید: «{$request->subject}»", "{$user->name} یک تیکت جدید با موضوع «{$request->subject}» ثبت کرد.");
        ActivityLog::record('ticket_create', 'ticket', "ثبت تیکت جدید «{$request->subject}» توسط {$user->name}", $user->id);

        return redirect()->route('tickets.show', $ticket->id)->with('success', 'تیکت شما ثبت شد.');
    }

    public function show(Request $request, int $id)
    {
        $ticket = Ticket::with('messages.user')->where('user_id', $request->user()->id)->findOrFail($id);

        return Inertia::render('Tickets/Show', ['ticket' => $this->presentTicket($ticket)]);
    }

    public function reply(Request $request, int $id)
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $user = $request->user();
        $ticket = Ticket::where('user_id', $user->id)->findOrFail($id);
        if ($ticket->status === 'closed') {
            return back()->with('error', 'این تیکت بسته شده و امکان پاسخ‌دهی ندارد.');
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id, 'user_id' => $user->id, 'is_admin' => false,
            'message' => $request->message, 'created_at' => now(),
        ]);
        $ticket->update(['status' => 'open']);

        $this->notifyAllAdmins("پاسخ کاربر در تیکت «{$ticket->subject}»", "{$user->name} در تیکت «{$ticket->subject}» پاسخ تازه‌ای ثبت کرد.");
        ActivityLog::record('ticket_reply', 'ticket', "پاسخ کاربر {$user->name} در تیکت «{$ticket->subject}»", $user->id);

        return back()->with('success', 'پاسخ شما ارسال شد.');
    }

    private function presentTicket(Ticket $ticket): array
    {
        return [
            'id'      => $ticket->id,
            'subject' => $ticket->subject,
            'status'  => $ticket->status,
            'messages' => $ticket->messages->map(fn ($m) => [
                'id'         => $m->id,
                'is_admin'   => $m->is_admin,
                // نام ادمین برای کاربر نشان داده نمی‌شود — طبق قاعده‌ی همیشگی پاسخ‌های ادمین
                'message'    => $m->message,
                'created_at' => Jalali::format($m->created_at),
            ]),
        ];
    }

    private function notifyAllAdmins(string $title, string $body): void
    {
        User::where('is_admin', true)->get()->each(function ($admin) use ($title, $body) {
            Notification::create(['user_id' => $admin->id, 'title' => $title, 'body' => $body, 'type' => 'system']);
        });
    }
}
