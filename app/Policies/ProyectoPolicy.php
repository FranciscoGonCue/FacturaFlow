<?php

namespace App\Policies;

use App\Models\Proyecto;
use App\Models\User;

/**
 * Autorización: un freelancer solo puede ver y tocar sus propios proyectos.
 * Laravel encuentra esta Policy sola por convención de nombres (Proyecto → ProyectoPolicy).
 */
class ProyectoPolicy
{
    public function view(User $user, Proyecto $proyecto): bool
    {
        return $user->id === $proyecto->user_id;
    }

    public function update(User $user, Proyecto $proyecto): bool
    {
        return $user->id === $proyecto->user_id;
    }

    public function delete(User $user, Proyecto $proyecto): bool
    {
        return $user->id === $proyecto->user_id;
    }
}
