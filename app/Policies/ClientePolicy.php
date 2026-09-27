<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * Autorización: un freelancer solo puede ver y tocar sus propios clientes.
 * Laravel encuentra esta Policy sola por convención de nombres (Cliente → ClientePolicy).
 */
class ClientePolicy
{
    public function view(User $user, Cliente $cliente): bool
    {
        return $user->id === $cliente->user_id;
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->id === $cliente->user_id;
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->id === $cliente->user_id;
    }
}
