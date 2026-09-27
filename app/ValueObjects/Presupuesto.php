<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Objeto de valor inmutable (readonly) con el desglose económico de un proyecto.
 *
 * Todos los importes se guardan en CÉNTIMOS (int) y no en euros (float):
 * los float acumulan errores de redondeo (0.1 + 0.2 !== 0.3) y con dinero eso no es aceptable.
 */
final readonly class Presupuesto
{
    public function __construct(
        public int $baseCentimos,
        public int $ivaCentimos,
        public int $irpfCentimos,
    ) {}

    /**
     * Total que paga el cliente: base + IVA − retención de IRPF.
     */
    public function totalCentimos(): int
    {
        return $this->baseCentimos + $this->ivaCentimos - $this->irpfCentimos;
    }

    public function base(): string
    {
        return self::formatear($this->baseCentimos);
    }

    public function iva(): string
    {
        return self::formatear($this->ivaCentimos);
    }

    public function irpf(): string
    {
        return self::formatear($this->irpfCentimos);
    }

    public function total(): string
    {
        return self::formatear($this->totalCentimos());
    }

    /**
     * Convierte céntimos a formato español: 123456 → "1.234,56 €".
     */
    public static function formatear(int $centimos): string
    {
        return number_format($centimos / 100, 2, ',', '.').' €';
    }
}
