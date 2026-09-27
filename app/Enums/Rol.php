<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Etiquetable;

/**
 * Roles de la aplicación.
 * - Freelancer: gestiona SUS clientes, proyectos, facturas y etiquetas.
 * - Administrador: además, accede a la zona /admin (usuarios y estadísticas globales).
 */
enum Rol: string implements Etiquetable
{
    case Freelancer = 'freelancer';
    case Administrador = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Freelancer => __('Freelancer'),
            self::Administrador => __('Administrator'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Freelancer => 'zinc',
            self::Administrador => 'violet',
        };
    }
}
