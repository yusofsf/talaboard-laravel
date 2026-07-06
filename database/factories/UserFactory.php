<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'phone'    => '09' . fake()->unique()->numerify('#########'),
            'email'    => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'salt'     => Str::random(32),
            'is_vip'   => false,
            'is_admin' => false,
            'membership_level'  => 1,
            'membership_status' => 'none',
        ];
    }

    public function vip(): static
    {
        return $this->state(fn () => ['is_vip' => true, 'membership_level' => 2, 'membership_status' => 'approved']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }
}
