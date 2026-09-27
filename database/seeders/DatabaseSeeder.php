<?php

namespace Database\Seeders;

use App\Enums\EstadoProyecto;
use App\Enums\TipoCliente;
use App\Models\Cliente;
use App\Models\Etiqueta;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\FacturaService;
use Carbon\CarbonImmutable;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Cinco freelancers de ejemplo. Todos tienen la contraseña "password".
     *
     * @var list<array{name: string, email: string, ciudad: string, direccion: string, clientes: int}>
     */
    private const array FREELANCERS = [
        ['name' => 'Laura Martín', 'email' => 'demo@facturaflow.test', 'ciudad' => 'Madrid', 'direccion' => 'Calle de Alcalá 120, 28009', 'clientes' => 8],
        ['name' => 'Javier Ruiz', 'email' => 'javier@facturaflow.test', 'ciudad' => 'Valencia', 'direccion' => 'Avenida del Puerto 45, 46023', 'clientes' => 6],
        ['name' => 'Lucía Fernández', 'email' => 'lucia@facturaflow.test', 'ciudad' => 'Barcelona', 'direccion' => 'Carrer de Mallorca 210, 08036', 'clientes' => 6],
        ['name' => 'Marcos Gil', 'email' => 'marcos@facturaflow.test', 'ciudad' => 'Sevilla', 'direccion' => 'Calle Sierpes 12, 41004', 'clientes' => 5],
        ['name' => 'Sofía Navarro', 'email' => 'sofia@facturaflow.test', 'ciudad' => 'Bilbao', 'direccion' => 'Gran Vía 55, 48011', 'clientes' => 5],
    ];

    /**
     * Etiquetas que tendrá cada freelancer (nombre => color de Flux).
     *
     * @var array<string, string>
     */
    private const array ETIQUETAS = [
        'Web' => 'sky',
        'E-commerce' => 'violet',
        'Mantenimiento' => 'teal',
        'Urgente' => 'red',
        'Diseño' => 'pink',
        'Móvil' => 'amber',
    ];

    public function run(FacturaService $facturas): void
    {
        // Administrador: gestiona usuarios y ve las estadísticas globales.
        User::factory()->administrador()->conDatosFiscales()->create([
            'name' => 'Ana Torres',
            'email' => 'admin@facturaflow.test',
        ]);

        // Una cuenta bloqueada, para demostrar que no puede iniciar sesión.
        User::factory()->bloqueado()->create([
            'name' => 'Cuenta Bloqueada',
            'email' => 'bloqueado@facturaflow.test',
        ]);

        foreach (self::FREELANCERS as $datos) {
            $usuario = User::factory()->create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'nif' => ClienteFactory::dni(),
                'direccion' => $datos['direccion'],
                'ciudad' => $datos['ciudad'],
                'iban' => 'ES'.fake()->numerify('######################'),
            ]);

            $clientes = Cliente::factory($datos['clientes'])->for($usuario)->create();

            foreach ($clientes as $cliente) {
                Proyecto::factory(fake()->numberBetween(1, 3))->for($cliente)->create();
            }

            if ($usuario->email === 'demo@facturaflow.test') {
                $this->casosDeDemo($usuario);
            }

            $this->etiquetarProyectos($usuario);
            $this->crearFacturas($usuario, $facturas);
        }
    }

    /**
     * Crea las etiquetas del usuario y asigna entre 0 y 3 a cada proyecto (relación muchos a muchos).
     */
    private function etiquetarProyectos(User $usuario): void
    {
        $etiquetas = collect(self::ETIQUETAS)->map(
            fn (string $color, string $nombre): Etiqueta => $usuario->etiquetas()->create(['nombre' => $nombre, 'color' => $color])
        )->values();

        foreach ($usuario->proyectos as $proyecto) {
            // attach() inserta filas en la tabla pivote etiqueta_proyecto.
            $proyecto->etiquetas()->attach(
                $etiquetas->random(fake()->numberBetween(0, 3))->pluck('id')
            );
        }
    }

    /**
     * Casos que conviene tener siempre en la demo: un proyecto retrasado y uno que se entrega pronto.
     */
    private function casosDeDemo(User $usuario): void
    {
        $cliente = Cliente::factory()->for($usuario)->tipo(TipoCliente::Empresa)->create([
            'nombre' => 'Nexo Digital S.L.',
            'email' => 'proyectos@nexodigital.test',
            'ciudad' => 'Madrid',
        ]);

        Proyecto::factory()->for($cliente)->estado(EstadoProyecto::EnCurso)->create([
            'nombre' => 'Plataforma de e-learning',
            'tarifa_hora' => 60,
            'horas_estimadas' => 120,
            'fecha_inicio' => now()->subMonths(2),
            'fecha_entrega' => now()->subDays(3),
        ]);

        Proyecto::factory()->for($cliente)->estado(EstadoProyecto::EnCurso)->create([
            'nombre' => 'App móvil de fidelización',
            'tarifa_hora' => 55,
            'horas_estimadas' => 80,
            'fecha_inicio' => now()->subMonth(),
            'fecha_entrega' => now()->addDays(4),
        ]);
    }

    /**
     * Planifica las facturas del usuario y las emite EN ORDEN DE FECHA,
     * para que la numeración correlativa coincida con el calendario (como exige la ley).
     */
    private function crearFacturas(User $usuario, FacturaService $servicio): void
    {
        $hoy = CarbonImmutable::today();
        $plan = [];

        foreach ($usuario->proyectos()->with('cliente')->get() as $proyecto) {
            $base = ['cliente_id' => $proyecto->cliente_id, 'proyecto_id' => $proyecto->id, 'dias_pago' => fake()->randomElement([15, 30, 30, 45])];

            if ($proyecto->estado === EstadoProyecto::Completado) {
                // Proyecto terminado: factura final, casi siempre cobrada.
                $plan[] = [
                    'datos' => $base + ['concepto' => $proyecto->nombre],
                    'lineas' => [['descripcion' => 'Desarrollo: '.$proyecto->nombre, 'cantidad' => $proyecto->horas_estimadas, 'precio' => (float) $proyecto->tarifa_hora]],
                    'fecha' => CarbonImmutable::parse($proyecto->fecha_entrega)->min($hoy),
                    'cobrada' => fake()->boolean(85),
                ];
            } elseif ($proyecto->estado === EstadoProyecto::EnCurso && fake()->boolean(60)) {
                // Proyecto en marcha: anticipo del 50 %.
                $mitad = round((float) $proyecto->tarifa_hora * $proyecto->horas_estimadas / 2, 2);
                $plan[] = [
                    'datos' => $base + ['concepto' => 'Anticipo 50 % — '.$proyecto->nombre],
                    'lineas' => [['descripcion' => 'Anticipo del 50 % del presupuesto aceptado', 'cantidad' => 1, 'precio' => $mitad]],
                    'fecha' => CarbonImmutable::parse($proyecto->fecha_inicio)->min($hoy),
                    'cobrada' => fake()->boolean(75),
                ];
            } elseif ($proyecto->estado === EstadoProyecto::Propuesta && fake()->boolean(40)) {
                // Propuesta: un borrador preparado pero sin emitir.
                $plan[] = [
                    'datos' => $base + ['concepto' => 'Presupuesto — '.$proyecto->nombre],
                    'lineas' => [
                        ['descripcion' => 'Análisis y diseño', 'cantidad' => (int) ceil($proyecto->horas_estimadas * 0.3), 'precio' => (float) $proyecto->tarifa_hora],
                        ['descripcion' => 'Desarrollo e implantación', 'cantidad' => (int) floor($proyecto->horas_estimadas * 0.7), 'precio' => (float) $proyecto->tarifa_hora],
                    ],
                    'fecha' => null,
                    'cobrada' => false,
                ];
            }
        }

        // Una cuota de mantenimiento mensual durante los últimos 6 meses (da vida al gráfico del panel).
        $clienteFijo = $usuario->clientes()->inRandomOrder()->first();
        $cuota = fake()->randomElement([150, 180, 220, 250, 300]);
        foreach (range(5, 0) as $haceMeses) {
            $fecha = $hoy->startOfMonth()->subMonths($haceMeses)->addDays(fake()->numberBetween(0, 4))->min($hoy);
            $plan[] = [
                'datos' => ['cliente_id' => $clienteFijo->id, 'proyecto_id' => null, 'dias_pago' => 15, 'concepto' => 'Mantenimiento '.$fecha->translatedFormat('F Y')],
                'lineas' => [
                    ['descripcion' => 'Mantenimiento web y actualizaciones de seguridad', 'cantidad' => 1, 'precio' => $cuota],
                    ['descripcion' => 'Horas de soporte', 'cantidad' => fake()->numberBetween(1, 4), 'precio' => 40],
                ],
                'fecha' => $fecha,
                'cobrada' => $haceMeses > 0,
            ];
        }

        // La cuenta demo siempre tiene un borrador listo para enseñar cómo se emite.
        if ($usuario->email === 'demo@facturaflow.test') {
            $proyecto = $usuario->proyectos()->where('nombre', 'App móvil de fidelización')->firstOrFail();
            $plan[] = [
                'datos' => ['cliente_id' => $proyecto->cliente_id, 'proyecto_id' => $proyecto->id, 'dias_pago' => 30, 'concepto' => 'Hito 1 — App móvil de fidelización'],
                'lineas' => [
                    ['descripcion' => 'Diseño de pantallas (Figma)', 'cantidad' => 24, 'precio' => 55],
                    ['descripcion' => 'Desarrollo del login y del perfil', 'cantidad' => 16, 'precio' => 55],
                    ['descripcion' => 'Licencia anual de notificaciones push', 'cantidad' => 1, 'precio' => 120],
                ],
                'fecha' => null,
                'cobrada' => false,
            ];
        }

        // Primero las que tienen fecha (ordenadas), después los borradores.
        usort($plan, fn (array $a, array $b): int => ($a['fecha'] === null ? PHP_INT_MAX : $a['fecha']->timestamp) <=> ($b['fecha'] === null ? PHP_INT_MAX : $b['fecha']->timestamp));

        foreach ($plan as $factura) {
            $borrador = $servicio->guardarBorrador($usuario, $factura['datos'], $factura['lineas']);

            if ($factura['fecha'] === null) {
                continue;
            }

            $emitida = $servicio->emitir($borrador, $factura['fecha']);

            $cobro = $factura['fecha']->addDays(fake()->numberBetween(3, $emitida->dias_pago + 10));
            if ($factura['cobrada'] && $cobro->lte($hoy)) {
                $servicio->marcarPagada($emitida, $cobro);
            }
        }
    }
}
