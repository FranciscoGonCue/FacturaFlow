<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Etiquetable;

/**
 * Ciclo de vida de un proyecto. Se guarda en la base de datos como string ('en_curso'...)
 * y Eloquent lo convierte automáticamente en este enum gracias al cast del modelo.
 */
enum EstadoProyecto: string implements Etiquetable
{
    case Propuesta = 'propuesta';
    case EnCurso = 'en_curso';
    case Pausado = 'pausado';
    case Completado = 'completado';

    public function label(): string
    {
        return match ($this) {
            self::Propuesta => __('Proposal'),
            self::EnCurso => __('In progress'),
            self::Pausado => __('Paused'),
            self::Completado => __('Completed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Propuesta => 'sky',
            self::EnCurso => 'indigo',
            self::Pausado => 'amber',
            self::Completado => 'emerald',
        };
    }

    /**
     * Un proyecto "activo" es el que todavía no se ha terminado.
     */
    public function esActivo(): bool
    {
        return $this !== self::Completado;
    }

    /**
     * Estados activos, útil para consultas whereIn().
     *
     * @return list<self>
     */
    public static function activos(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $estado): bool => $estado->esActivo(),
        ));
    }
}
