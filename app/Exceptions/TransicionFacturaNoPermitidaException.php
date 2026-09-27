<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoFactura;
use App\Models\Factura;

/**
 * Se lanza al intentar un cambio de estado imposible, por ejemplo cobrar un borrador
 * o volver a emitir una factura ya enviada.
 */
class TransicionFacturaNoPermitidaException extends FacturaFlowException
{
    public function __construct(Factura $factura, EstadoFactura $destino)
    {
        parent::__construct(__('An invoice in status ":from" cannot change to ":to".', [
            'from' => $factura->estado->label(),
            'to' => $destino->label(),
        ]));
    }
}
