<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Exceptions\TransicionFacturaNoPermitidaException;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Toda la lógica de negocio de las facturas vive aquí, fuera del controlador:
 * guardar borradores, calcular importes, emitir (numerar) y registrar cobros.
 */
final class FacturaService
{
    public function __construct(
        private readonly CalculadoraPresupuesto $calculadora,
        private readonly NumeradorFacturas $numerador,
    ) {}

    /**
     * Crea o actualiza un BORRADOR con sus líneas y recalcula los importes.
     *
     * @param  array{cliente_id: int, proyecto_id?: int|null, concepto: string, dias_pago: int, notas?: string|null}  $datos
     * @param  list<array{descripcion: string, cantidad: float|int|string, precio: float|int|string}>  $lineas  precio en euros
     *
     * @throws TransicionFacturaNoPermitidaException si la factura ya no es un borrador
     */
    public function guardarBorrador(User $usuario, array $datos, array $lineas, ?Factura $factura = null): Factura
    {
        if ($factura !== null && ! $factura->estado->esEditable()) {
            throw new TransicionFacturaNoPermitidaException($factura, EstadoFactura::Borrador);
        }

        return DB::transaction(function () use ($usuario, $datos, $lineas, $factura): Factura {
            $factura ??= new Factura;
            $factura->fill($datos);
            $factura->user()->associate($usuario);
            $factura->estado = EstadoFactura::Borrador;
            $factura->save();

            // Lo más sencillo y seguro: borrar las líneas anteriores y guardar las nuevas en orden.
            $factura->lineas()->delete();
            foreach ($lineas as $orden => $linea) {
                $factura->lineas()->create([
                    'descripcion' => $linea['descripcion'],
                    'cantidad' => round((float) $linea['cantidad'], 2),
                    'precio_centimos' => (int) round((float) $linea['precio'] * 100),
                    'orden' => $orden,
                ]);
            }

            $this->recalcular($factura);

            return $factura->refresh();
        });
    }

    /**
     * Borrador → Enviada. Asigna el número oficial y las fechas.
     */
    public function emitir(Factura $factura, ?CarbonInterface $fecha = null): Factura
    {
        if ($factura->estado !== EstadoFactura::Borrador) {
            throw new TransicionFacturaNoPermitidaException($factura, EstadoFactura::Enviada);
        }

        $fecha ??= now();

        return DB::transaction(function () use ($factura, $fecha): Factura {
            $numeracion = $this->numerador->siguiente($factura->user, (int) $fecha->year);

            $factura->forceFill([
                ...$numeracion,
                'estado' => EstadoFactura::Enviada,
                'fecha_emision' => $fecha->toDateString(),
                'fecha_vencimiento' => $fecha->copy()->addDays($factura->dias_pago)->toDateString(),
            ])->save();

            return $factura;
        });
    }

    /**
     * Enviada → Pagada.
     */
    public function marcarPagada(Factura $factura, ?CarbonInterface $fecha = null): Factura
    {
        if ($factura->estado !== EstadoFactura::Enviada) {
            throw new TransicionFacturaNoPermitidaException($factura, EstadoFactura::Pagada);
        }

        $factura->forceFill([
            'estado' => EstadoFactura::Pagada,
            'pagada_en' => ($fecha ?? now())->toDateString(),
        ])->save();

        return $factura;
    }

    /**
     * Suma las líneas y aplica la fiscalidad del cliente. Los importes quedan guardados
     * en la factura para que no cambien nunca después de emitirla.
     */
    private function recalcular(Factura $factura): void
    {
        $factura->load('lineas');

        $base = $factura->lineas->sum(fn ($linea): int => $linea->importeCentimos());

        /** @var Cliente $cliente */
        $cliente = Cliente::query()->findOrFail($factura->cliente_id);
        $importes = $this->calculadora->desdeBase((int) $base, $cliente->tipo);

        $factura->forceFill([
            'base_centimos' => $importes->baseCentimos,
            'iva_centimos' => $importes->ivaCentimos,
            'irpf_centimos' => $importes->irpfCentimos,
            'total_centimos' => $importes->totalCentimos(),
        ])->save();
    }
}
