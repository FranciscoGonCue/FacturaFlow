<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TipoCliente;
use App\ValueObjects\Presupuesto;
use InvalidArgumentException;

/**
 * Calcula el presupuesto de un proyecto aplicando la fiscalidad de un freelancer en España.
 * No depende de Laravel ni de la base de datos: es PHP puro y se puede testear aislado.
 */
final class CalculadoraPresupuesto
{
    /** IVA general en España. */
    public const float IVA = 0.21;

    /** Retención de IRPF habitual para profesionales. */
    public const float IRPF = 0.15;

    public function calcular(float $tarifaHora, int $horas, TipoCliente $tipoCliente): Presupuesto
    {
        if ($tarifaHora < 0 || $horas < 0) {
            throw new InvalidArgumentException('La tarifa y las horas no pueden ser negativas.');
        }

        // Pasamos a céntimos una sola vez y a partir de aquí solo operamos con enteros.
        return $this->desdeBase((int) round($tarifaHora * 100) * $horas, $tipoCliente);
    }

    /**
     * Aplica IVA e IRPF a una base ya calculada en céntimos.
     * La usan tanto los presupuestos (tarifa × horas) como las facturas (suma de líneas).
     */
    public function desdeBase(int $baseCentimos, TipoCliente $tipoCliente): Presupuesto
    {
        if ($baseCentimos < 0) {
            throw new InvalidArgumentException('La base imponible no puede ser negativa.');
        }

        $iva = (int) round($baseCentimos * self::IVA);
        $irpf = $tipoCliente->aplicaRetencionIrpf() ? (int) round($baseCentimos * self::IRPF) : 0;

        return new Presupuesto($baseCentimos, $iva, $irpf);
    }
}
