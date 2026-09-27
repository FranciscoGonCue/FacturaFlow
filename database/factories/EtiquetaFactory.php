<?php

namespace Database\Factories;

use App\Models\Etiqueta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Etiqueta>
 */
class EtiquetaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => fake()->unique()->word(),
            'color' => fake()->randomElement(Etiqueta::COLORES),
        ];
    }
}
