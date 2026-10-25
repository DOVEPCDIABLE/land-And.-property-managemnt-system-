<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Role;
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
            'agency_id' => Agency::factory(),
            'role_id' => fn () => Role::firstOrCreate(['name' => 'client'])->id,
            'is_active' => true,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('TestPassword123!'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function role(string $name): static
    {
        return $this->state(fn () => ['role_id' => Role::where('name', $name)->sole()->id, ...($name === 'platform_admin' ? ['agency_id' => null] : [])]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
