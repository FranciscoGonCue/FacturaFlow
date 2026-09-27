<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato para los enums que se muestran como etiqueta (badge) en la interfaz.
 *
 * Al exigir label() y color() a todos los enums, el componente <x-etiqueta>
 * puede pintar cualquiera de ellos sin saber de qué enum se trata (polimorfismo).
 */
interface Etiquetable
{
    /** Texto legible (ya traducido al idioma activo). */
    public function label(): string;

    /** Color de Flux para el <flux:badge>: zinc, sky, indigo, amber, emerald, red… */
    public function color(): string;
}
