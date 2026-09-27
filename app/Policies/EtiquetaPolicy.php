<?php

namespace App\Policies;

use App\Models\Etiqueta;
use App\Models\User;

/**
 * Autorización: un freelancer solo puede ver y tocar sus propias etiquetas.
 * Laravel encuentra esta Policy sola por convención de nombres (Etiqueta → EtiquetaPolicy).
 */
class EtiquetaPolicy
{
    public function view(User $user, Etiqueta $etiqueta): bool
    {
        return $user->id === $etiqueta->user_id;
    }

    public function update(User $user, Etiqueta $etiqueta): bool
    {
        return $user->id === $etiqueta->user_id;
    }

    public function delete(User $user, Etiqueta $etiqueta): bool
    {
        return $user->id === $etiqueta->user_id;
    }
}
