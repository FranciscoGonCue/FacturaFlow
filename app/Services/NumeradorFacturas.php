<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Factura;
use App\Models\User;

/**
 * Genera números de factura correlativos por usuario y año: FF-2026-0001, FF-2026-0002…
 * En España la numeración de facturas debe ser correlativa y sin huecos dentro de cada serie.
 */
final class NumeradorFacturas
{
    public const string PREFIJO = 'FF';

    /**
     * Debe llamarse DENTRO de una transacción: el bloqueo evita que dos facturas
     * emitidas a la vez reciban el mismo número (además, la base de datos tiene un índice único).
     *
     * @return array{anio: int, secuencia: int, numero: string}
     */
    public function siguiente(User $usuario, int $anio): array
    {
        $ultima = (int) Factura::query()
            ->where('user_id', $usuario->id)
            ->where('anio', $anio)
            ->lockForUpdate()
            ->max('secuencia');

        $secuencia = $ultima + 1;

        return [
            'anio' => $anio,
            'secuencia' => $secuencia,
            'numero' => self::formatear($anio, $secuencia),
        ];
    }

    public static function formatear(int $anio, int $secuencia): string
    {
        return sprintf('%s-%d-%04d', self::PREFIJO, $anio, $secuencia);
    }
}
