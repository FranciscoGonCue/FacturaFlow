<?php

namespace Database\Factories;

use App\Enums\EstadoFactura;
use App\Models\Cliente;
use App\Models\Factura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crea borradores. Para emitir o cobrar se usa FacturaService, igual que en la app real,
 * así los números y los importes siempre son coherentes.
 *
 * @extends Factory<Factura>
 */
class FacturaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'user_id' => fn (array $atributos): int => (int) Cliente::query()->whereKey($atributos['cliente_id'])->value('user_id'),
            'concepto' => fake('es_ES')->randomElement(['Desarrollo web', 'Mantenimiento mensual', 'Consultoría técnica', 'Diseño de interfaz']),
            'estado' => EstadoFactura::Borrador,
            'dias_pago' => 30,
        ];
    }
}
