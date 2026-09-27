<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Etiquetable;

/**
 * Ciclo de vida de una factura: Borrador → Enviada → Pagada.
 *
 * "Vencida" NO es un estado guardado: una factura enviada pasa a estar vencida
 * sola, con el paso del tiempo. Por eso se calcula (Factura::estaVencida()).
 */
enum EstadoFactura: string implements Etiquetable
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case Pagada = 'pagada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => __('Draft'),
            self::Enviada => __('Sent'),
            self::Pagada => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'zinc',
            self::Enviada => 'sky',
            self::Pagada => 'emerald',
        };
    }

    /**
     * Solo los borradores se pueden modificar o borrar: una factura emitida es un documento legal.
     */
    public function esEditable(): bool
    {
        return $this === self::Borrador;
    }
}
