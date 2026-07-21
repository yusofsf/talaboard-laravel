<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserPassword
{
    /**
     * Shared policy for every password-creation flow.
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(10)->mixedCase()->numbers()->symbols(),
        ];
    }

    public static function newSalt(): string
    {
        return Str::random(32);
    }

    public static function hash(string $plainPassword, string $salt): string
    {
        return Hash::make(self::saltedInput($plainPassword, $salt));
    }

    public static function saltedInput(string $plainPassword, string $salt): string
    {
        return hash_hmac('sha256', $plainPassword, $salt);
    }

    public static function check(User $user, string $plainPassword): bool
    {
        if (filled($user->salt) && Hash::check(self::saltedInput($plainPassword, $user->salt), $user->password)) {
            return true;
        }

        return Hash::check($plainPassword, $user->password);
    }

    public static function checkAndUpgrade(User $user, string $plainPassword): bool
    {
        if (filled($user->salt) && Hash::check(self::saltedInput($plainPassword, $user->salt), $user->password)) {
            return true;
        }

        if (!Hash::check($plainPassword, $user->password)) {
            return false;
        }

        self::set($user, $plainPassword);

        return true;
    }

    public static function set(User $user, string $plainPassword): void
    {
        $salt = self::newSalt();

        $user->forceFill([
            'salt' => $salt,
            'password' => self::hash($plainPassword, $salt),
        ])->save();
    }
}
