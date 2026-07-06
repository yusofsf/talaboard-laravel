<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\UserPassword;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salt = UserPassword::newSalt();

        return [
            'name'     => fake()->name(),
            'phone'    => '09' . fake()->unique()->numerify('#########'),
            'email'    => fake()->unique()->safeEmail(),
            'password' => UserPassword::hash('password', $salt),
            'salt'     => $salt,
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
