<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Cliente;

class ClienteConProyectosActivosException extends FacturaFlowException
{
    public function __construct(Cliente $cliente, int $proyectosActivos)
    {
        // trans_choice elige singular o plural según el número (1 proyecto / 3 proyectos).
        parent::__construct(trans_choice(
            'You cannot delete ":name": it has :count unfinished project.|You cannot delete ":name": it has :count unfinished projects.',
            $proyectosActivos,
            ['name' => $cliente->nombre, 'count' => $proyectosActivos],
        ));
    }
}
