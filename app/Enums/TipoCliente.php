<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Etiquetable;

/**
 * Tipo de cliente. Tiene consecuencias fiscales reales para un freelancer en España:
 * a empresas y autónomos se les aplica retención de IRPF; a particulares, no.
 */
enum TipoCliente: string implements Etiquetable
{
    case Empresa = 'empresa';
    case Autonomo = 'autonomo';
    case Particular = 'particular';

    public function label(): string
    {
        return match ($this) {
            self::Empresa => __('Company'),
            self::Autonomo => __('Self-employed'),
            self::Particular => __('Individual'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Empresa => 'violet',
            self::Autonomo => 'cyan',
            self::Particular => 'zinc',
        };
    }

    public function aplicaRetencionIrpf(): bool
    {
        return match ($this) {
            self::Empresa, self::Autonomo => true,
            self::Particular => false,
        };
    }
}
