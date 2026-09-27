<?php

namespace App\Policies;

use App\Models\User;

/**
 * Qué puede hacer un ADMINISTRADOR con las cuentas de los demás.
 * La regla clave: un admin nunca puede degradarse, bloquearse ni borrarse a sí mismo
 * (si no, la aplicación podría quedarse sin ningún administrador).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdministrador();
    }

    public function update(User $user, User $objetivo): bool
    {
        return $user->esAdministrador() && $user->isNot($objetivo);
    }

    public function delete(User $user, User $objetivo): bool
    {
        return $this->update($user, $objetivo);
    }
}
