<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'phone' => $this->faker->phoneNumber(),
            'country' => 'Perú',
            'birth_date' => $this->faker->date('Y-m-d', '2000-01-01'),
            'address' => $this->faker->address(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'preferred_language' => 'es',
            'active' => true,
            'last_login' => $this->faker->optional()->dateTimeBetween('-30 days'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Usuario no verificado
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuario inactivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Usuario administrador
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);
    }
}