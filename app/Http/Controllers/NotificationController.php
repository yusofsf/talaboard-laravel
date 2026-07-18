<?php

namespace App\Http\Controllers;

use App\Helpers\Jalali;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $readIds = NotificationRead::where('user_id', $user->id)->pluck('notification_id')->toArray();

        // اعلانی که کاربر خوانده، از لیست او حذف می‌شود (برای سایر گیرندگان احتمالی همچنان باقی می‌ماند)
        $notifs = $this->visibleNotificationsFor($user)
        ->whereNotIn('id', $readIds)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->get()
        ->map(fn($n) => [
            'id'         => $n->id,
            'title'      => $n->title,
            'body'       => $n->body,
            'type'       => $n->type,
            'created_at' => Jalali::format($n->created_at),
        ]);

        return Inertia::render('Notifications', ['notifications' => $notifs]);
    }

    public function markRead(Request $request, int $id)
    {
        $this->visibleNotificationsFor($request->user())->whereKey($id)->firstOrFail();

        NotificationRead::firstOrCreate([
            'notification_id' => $id,
            'user_id'         => $request->user()->id,
        ], ['read_at' => now()]);
        return back();
    }

    public function markAllRead(Request $request)
    {
        $uid     = $request->user()->id;
        $readIds = NotificationRead::where('user_id', $uid)->pluck('notification_id')->toArray();

        $this->visibleNotificationsFor($request->user())->whereNotIn('id', $readIds)->each(function ($n) use ($uid) {
            NotificationRead::firstOrCreate(
                ['notification_id' => $n->id, 'user_id' => $uid],
                ['read_at' => now()],
            );
        });
        return back();
    }

    private function visibleNotificationsFor($user)
    {
        return Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            });
    }
}
