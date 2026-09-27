<?php

namespace Database\Factories;

use App\Enums\Rol;
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
            'name' => fake('es_ES')->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => Rol::Freelancer,
            'activo' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function administrador(): static
    {
        return $this->state(fn (): array => ['rol' => Rol::Administrador]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }

    /**
     * Freelancer con los datos fiscales rellenos (necesarios para emitir facturas).
     */
    public function conDatosFiscales(): static
    {
        return $this->state(fn (): array => [
            'nif' => ClienteFactory::dni(),
            'direccion' => fake('es_ES')->streetAddress(),
            'ciudad' => fake('es_ES')->city(),
            'iban' => 'ES'.fake()->numerify('######################'),
        ]);
    }
}
