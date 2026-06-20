<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportFlaskShop extends Command
{
    protected $signature = 'import:flask-shop {path? : مسیر فایل shop.db نسخه‌ی قبلی Flask}';

    protected $description = 'ایمپورت کاربران، معاملات، کیف پول، اعلانات و کدهای دعوت از shop.db نسخه‌ی Flask';

    public function handle(): int
    {
        $path = $this->argument('path') ?: env('FLASK_SHOP_DB_PATH');
        if (!$path || !file_exists($path)) {
            $this->error("فایل shop.db پیدا نشد: {$path}");
            return self::FAILURE;
        }

        config(['database.connections.flask_shop.database' => $path]);
        DB::purge('flask_shop');
        $old = DB::connection('flask_shop');

        $this->info("در حال ایمپورت از: {$path}");

        DB::transaction(function () use ($old) {
            $userMap = $this->importUsers($old);
            $this->importTransactions($old);
            $this->importInviteCodes($old);
            $this->importWalletTransactions($old);
            $this->importNotifications($old);
        });

        $this->info('ایمپورت با موفقیت انجام شد.');
        $this->warn('توجه: رمزهای عبور قدیمی (Werkzeug scrypt) با سیستم Laravel سازگار نیستند.');
        $this->warn('همه‌ی کاربران ایمپورت‌شده باید رمز خود را از طریق «فراموشی رمز عبور» بازنشانی کنند.');

        return self::SUCCESS;
    }

    private function importUsers($old): void
    {
        $rows = $old->table('users')->orderBy('id')->get();
        $this->info("کاربران: {$rows->count()}");

        foreach ($rows as $r) {
            DB::table('users')->upsert([
                'id'                   => $r->id,
                'name'                 => $r->name,
                'phone'                => $r->phone,
                'email'                => $r->email ?: null,
                'national_id'          => $r->national_id ?: null,
                'password'             => Hash::make(bin2hex(random_bytes(16))), // غیرقابل ورود — باید بازنشانی شود
                'legacy_password_hash' => $r->password_hash,
                'must_reset_password'  => true,
                'is_admin'             => (bool) ($r->is_admin ?? false),
                'is_vip'               => (bool) ($r->is_vip ?? false),
                'created_at'           => $r->created_at,
                'updated_at'           => $r->created_at,
            ], ['id'], [
                'name', 'phone', 'email', 'national_id',
                'legacy_password_hash', 'must_reset_password',
                'is_admin', 'is_vip', 'created_at', 'updated_at',
            ]);
        }
    }

    private function importTransactions($old): void
    {
        $rows = $old->table('transactions')->orderBy('id')->get();
        $this->info("معاملات: {$rows->count()}");

        foreach ($rows->chunk(200) as $chunk) {
            $data = $chunk->map(fn ($r) => [
                'id'             => $r->id,
                'user_id'        => $r->user_id,
                'type'           => $r->type,
                'item'           => $r->item,
                'item_label'     => $r->item_label,
                'quantity'       => $r->quantity,
                'price_per_unit' => $r->price_per_unit,
                'total'          => $r->total,
                'created_at'     => $r->created_at,
                'updated_at'     => $r->created_at,
            ])->all();

            DB::table('transactions')->upsert($data, ['id'], [
                'user_id', 'type', 'item', 'item_label', 'quantity',
                'price_per_unit', 'total', 'created_at', 'updated_at',
            ]);
        }
    }

    private function importInviteCodes($old): void
    {
        $rows = $old->table('invite_codes')->orderBy('id')->get();
        $this->info("کدهای دعوت: {$rows->count()}");

        foreach ($rows as $r) {
            DB::table('invite_codes')->upsert([
                'id'         => $r->id,
                'code'       => $r->code,
                'used_by'    => $r->used_by,
                'used_at'    => $r->used_at,
                'created_at' => $r->created_at,
                'updated_at' => $r->created_at,
            ], ['id'], ['code', 'used_by', 'used_at', 'created_at', 'updated_at']);
        }
    }

    private function importWalletTransactions($old): void
    {
        $rows = $old->table('wallet_transactions')->orderBy('id')->get();
        $this->info("تراکنش‌های کیف پول: {$rows->count()}");

        foreach ($rows->chunk(200) as $chunk) {
            $data = $chunk->map(fn ($r) => [
                'id'          => $r->id,
                'user_id'     => $r->user_id,
                'amount'      => $r->amount,
                'type'        => $r->type,
                'description' => $r->description,
                'created_at'  => $r->created_at,
                'updated_at'  => $r->created_at,
            ])->all();

            DB::table('wallet_transactions')->upsert($data, ['id'], [
                'user_id', 'amount', 'type', 'description', 'created_at', 'updated_at',
            ]);
        }
    }

    private function importNotifications($old): void
    {
        $rows = $old->table('notifications')->orderBy('id')->get();
        $this->info("اعلانات: {$rows->count()}");

        foreach ($rows as $r) {
            DB::table('notifications')->upsert([
                'id'         => $r->id,
                'title'      => $r->title,
                'body'       => $r->body,
                'type'       => $r->notif_type,
                'user_id'    => $r->user_id,
                'created_at' => $r->created_at,
                'updated_at' => $r->created_at,
            ], ['id'], ['title', 'body', 'type', 'user_id', 'created_at', 'updated_at']);
        }

        $reads = $old->table('notification_reads')->get();
        $this->info("وضعیت خوانده‌شده: {$reads->count()}");

        foreach ($reads as $r) {
            DB::table('notification_reads')->upsert([
                'notification_id' => $r->notif_id,
                'user_id'         => $r->user_id,
                'read_at'         => $r->read_at,
            ], ['notification_id', 'user_id'], ['read_at']);
        }
    }
}
