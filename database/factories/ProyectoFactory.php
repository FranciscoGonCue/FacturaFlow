<?php

namespace Database\Factories;

use App\Enums\EstadoProyecto;
use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proyecto>
 */
class ProyectoFactory extends Factory
{
    /** @var list<string> */
    private const array TRABAJOS = [
        'Rediseño de la web corporativa',
        'Tienda online con pasarela de pago',
        'App de reservas para clínica',
        'Panel de analítica interno',
        'Migración a Laravel 13',
        'Landing page de lanzamiento',
        'Integración con API de facturación',
        'Auditoría de accesibilidad',
        'Chatbot de atención al cliente',
        'Mantenimiento mensual WordPress',
        'Sistema de gestión de inventario',
        'Portal del empleado',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Más proyectos en curso que en otros estados, como en la vida real.
        $estado = fake()->randomElement([
            EstadoProyecto::Propuesta,
            EstadoProyecto::EnCurso, EstadoProyecto::EnCurso, EstadoProyecto::EnCurso,
            EstadoProyecto::Pausado,
            EstadoProyecto::Completado, EstadoProyecto::Completado,
        ]);

        // Fechas coherentes: lo completado ya se entregó; lo activo se entrega en el futuro.
        if ($estado === EstadoProyecto::Completado) {
            $inicio = fake()->dateTimeBetween('-8 months', '-2 months');
            $entrega = fake()->dateTimeBetween($inicio, '-1 week');
        } else {
            $inicio = fake()->dateTimeBetween('-2 months', 'now');
            $entrega = fake()->dateTimeBetween('+5 days', '+3 months');
        }

        return [
            'cliente_id' => Cliente::factory(),
            // El proyecto pertenece al mismo freelancer que su cliente.
            'user_id' => fn (array $atributos): int => (int) Cliente::query()->whereKey($atributos['cliente_id'])->value('user_id'),
            'nombre' => fake()->randomElement(self::TRABAJOS),
            'descripcion' => fake('es_ES')->optional(0.7)->paragraph(),
            'estado' => $estado,
            'tarifa_hora' => fake()->randomElement([35, 40, 45, 50, 55, 60, 75]),
            'horas_estimadas' => fake()->numberBetween(8, 160),
            'fecha_inicio' => $inicio,
            'fecha_entrega' => $entrega,
        ];
    }

    public function estado(EstadoProyecto $estado): static
    {
        return $this->state(fn (): array => ['estado' => $estado]);
    }
}
