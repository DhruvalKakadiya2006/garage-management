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
     * Factory users default to admin so existing feature tests exercising
     * destructive endpoints keep working; real registrations are staff by
     * default (DB default false). Non-admin scenarios use staff().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => true,
        ];
    }

    /**
     * Indicate that the user is regular staff (non-admin).
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => false,
        ]);
    }
}
