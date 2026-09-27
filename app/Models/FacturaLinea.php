<?php

namespace App\Models;

use App\ValueObjects\Presupuesto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la factura: "Desarrollo web · 20 h × 50,00 €".
 *
 * @property int $id
 * @property int $factura_id
 * @property string $descripcion
 * @property string $cantidad
 * @property int $precio_centimos
 * @property int $orden
 */
class FacturaLinea extends Model
{
    protected $table = 'factura_lineas';

    /** @var list<string> */
    protected $fillable = ['descripcion', 'cantidad', 'precio_centimos', 'orden'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_centimos' => 'integer',
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<Factura, $this> */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function importeCentimos(): int
    {
        return (int) round((float) $this->cantidad * $this->precio_centimos);
    }

    public function precio(): string
    {
        return Presupuesto::formatear($this->precio_centimos);
    }

    public function importe(): string
    {
        return Presupuesto::formatear($this->importeCentimos());
    }
}
