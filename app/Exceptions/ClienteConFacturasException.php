<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Cliente;

class ClienteConFacturasException extends FacturaFlowException
{
    public function __construct(Cliente $cliente)
    {
        parent::__construct(__('You cannot delete ":name": it has issued invoices that must be kept by law.', [
            'name' => $cliente->nombre,
        ]));
    }
}
