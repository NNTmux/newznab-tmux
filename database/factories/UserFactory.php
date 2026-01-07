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
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

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
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'roles_id' => 1,
            'api_token' => md5(Str::random(32)),
            'grabs' => 0,
            'invites' => 0,
            'notes' => '',
            'movieview' => true,
            'xxxview' => false,
            'musicview' => true,
            'consoleview' => true,
            'bookview' => true,
            'gameview' => true,
            'verified' => true,
            'can_post' => true,
            'rate_limit' => 60,
            'email_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'verified' => false,
        ]);
    }

    /**
     * Set a specific role for the user.
     */
    public function withRole(int $roleId): static
    {
        return $this->state(fn (array $attributes) => [
            'roles_id' => $roleId,
        ]);
    }

    /**
     * Set a role expiration date.
     */
    public function withRoleExpiration(\DateTimeInterface|string|null $date): static
    {
        return $this->state(fn (array $attributes) => [
            'rolechangedate' => $date,
        ]);
    }
}
