<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Factura;
use App\ValueObjects\Presupuesto;
use Illuminate\Support\Collection;

/**
 * Calcula lo facturado en cada uno de los últimos N meses (lo usan el panel del
 * freelancer y el panel del administrador para el gráfico de barras).
 */
final class FacturacionMensual
{
    /**
     * @param  Collection<int, Factura>  $facturas  facturas ya emitidas
     * @return list<array{etiqueta: string, centimos: int, texto: string, porcentaje: int}>
     */
    public static function calcular(Collection $facturas, int $meses = 6): array
    {
        $datos = collect(range($meses - 1, 0))->map(function (int $haceMeses) use ($facturas): array {
            $mes = now()->startOfMonth()->subMonths($haceMeses);
            $centimos = (int) $facturas
                ->filter(fn (Factura $f): bool => $f->fecha_emision?->isSameMonth($mes) === true)
                ->sum('total_centimos');

            return ['etiqueta' => ucfirst($mes->translatedFormat('M')), 'centimos' => $centimos];
        });

        $maximo = max(1, (int) $datos->max('centimos'));

        return array_values($datos->map(fn (array $m): array => [
            ...$m,
            'texto' => Presupuesto::formatear($m['centimos']),
            'porcentaje' => (int) round($m['centimos'] / $maximo * 100),
        ])->all());
    }
}
