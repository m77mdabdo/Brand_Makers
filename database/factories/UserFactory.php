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
    protected $model = User::class;

    /**
     * Hashed once per process rather than per row — bcrypt is deliberately
     * slow, and a test creating fifty users should not pay for it fifty
     * times.
     */
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'locale' => 'ar',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * is_admin is guarded, so it cannot be set through the definition
     * array — the factory has to assign it after the model is built, the
     * same way real code does.
     */
    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->is_admin = true)
            ->afterCreating(function (User $user) {
                $user->is_admin = true;
                $user->save();
            });
    }
}
