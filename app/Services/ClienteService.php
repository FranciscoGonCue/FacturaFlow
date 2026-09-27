<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoFactura;
use App\Exceptions\ClienteConFacturasException;
use App\Exceptions\ClienteConProyectosActivosException;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio de los clientes que no caben en un simple CRUD.
 */
final class ClienteService
{
    /**
     * Elimina un cliente, sus proyectos terminados y sus borradores de factura.
     *
     * @throws ClienteConProyectosActivosException si todavía tiene trabajo en marcha.
     * @throws ClienteConFacturasException si tiene facturas emitidas (hay que conservarlas).
     */
    public function eliminar(Cliente $cliente): void
    {
        $activos = $cliente->proyectos()->activos()->count();

        if ($activos > 0) {
            throw new ClienteConProyectosActivosException($cliente, $activos);
        }

        if ($cliente->facturas()->where('estado', '!=', EstadoFactura::Borrador)->exists()) {
            throw new ClienteConFacturasException($cliente);
        }

        // Transacción: o se borra todo, o no se borra nada.
        DB::transaction(function () use ($cliente): void {
            $cliente->facturas()->delete();
            $cliente->proyectos()->delete();
            $cliente->delete();
        });
    }
}
