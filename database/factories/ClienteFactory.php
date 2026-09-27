<?php

namespace Database\Factories;

use App\Enums\TipoCliente;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = fake('es_ES');
        $tipo = $faker->randomElement(TipoCliente::cases());

        return [
            'user_id' => User::factory(),
            'tipo' => $tipo,
            'nombre' => $tipo === TipoCliente::Empresa ? $faker->company() : $faker->name(),
            'email' => $faker->unique()->safeEmail(),
            'telefono' => $faker->optional(0.8)->phoneNumber(),
            'nif' => $tipo === TipoCliente::Particular ? self::dni() : self::cif(),
            'ciudad' => $faker->city(),
            'notas' => $faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * DNI válido: 8 cifras + la letra de control oficial (resto de dividir entre 23).
     */
    public static function dni(): string
    {
        $numero = fake()->numberBetween(10_000_000, 99_999_999);

        return $numero.'TRWAGMYFPDXBNJZSQVHLCKE'[$numero % 23];
    }

    /**
     * CIF de ejemplo de sociedad limitada (letra B + 8 cifras).
     */
    public static function cif(): string
    {
        return 'B'.fake()->numerify('########');
    }

    public function tipo(TipoCliente $tipo): static
    {
        return $this->state(fn (): array => ['tipo' => $tipo]);
    }
}
