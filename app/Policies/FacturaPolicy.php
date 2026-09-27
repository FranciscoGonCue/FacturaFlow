<?php

namespace App\Policies;

use App\Enums\EstadoFactura;
use App\Models\Factura;
use App\Models\User;

/**
 * Además de comprobar el dueño, la Policy aplica las reglas del ciclo de vida:
 * solo se edita o borra un borrador, solo se emite un borrador y solo se cobra una enviada.
 */
class FacturaPolicy
{
    public function view(User $user, Factura $factura): bool
    {
        return $user->id === $factura->user_id;
    }

    public function update(User $user, Factura $factura): bool
    {
        return $this->view($user, $factura) && $factura->estado->esEditable();
    }

    public function delete(User $user, Factura $factura): bool
    {
        return $this->update($user, $factura);
    }

    public function emitir(User $user, Factura $factura): bool
    {
        return $this->view($user, $factura) && $factura->estado === EstadoFactura::Borrador;
    }

    public function pagar(User $user, Factura $factura): bool
    {
        return $this->view($user, $factura) && $factura->estado === EstadoFactura::Enviada;
    }
}
