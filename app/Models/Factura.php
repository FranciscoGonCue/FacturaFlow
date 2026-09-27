<?php

namespace App\Models;

use App\Enums\EstadoFactura;
use App\ValueObjects\Presupuesto;
use Database\Factories\FacturaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $cliente_id
 * @property int|null $proyecto_id
 * @property int|null $anio
 * @property int|null $secuencia
 * @property string|null $numero
 * @property string $concepto
 * @property EstadoFactura $estado
 * @property int $dias_pago
 * @property Carbon|null $fecha_emision
 * @property Carbon|null $fecha_vencimiento
 * @property Carbon|null $pagada_en
 * @property int $base_centimos
 * @property int $iva_centimos
 * @property int $irpf_centimos
 * @property int $total_centimos
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Cliente $cliente
 * @property-read Proyecto|null $proyecto
 * @property-read Collection<int, FacturaLinea> $lineas
 */
class Factura extends Model
{
    /** @use HasFactory<FacturaFactory> */
    use HasFactory;

    protected $table = 'facturas';

    /**
     * Solo los campos que el usuario puede rellenar en el formulario.
     * Número, estado e importes los gestiona FacturaService, nunca el formulario.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cliente_id',
        'proyecto_id',
        'concepto',
        'dias_pago',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoFactura::class,
            'dias_pago' => 'integer',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'pagada_en' => 'date',
            'base_centimos' => 'integer',
            'iva_centimos' => 'integer',
            'irpf_centimos' => 'integer',
            'total_centimos' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Proyecto, $this> */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    /** @return HasMany<FacturaLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(FacturaLinea::class)->orderBy('orden');
    }

    /**
     * Facturas enviadas cuyo plazo de pago ya ha pasado.
     *
     * @param  Builder<Factura>  $query
     */
    public function scopeVencidas(Builder $query): void
    {
        $query->where('estado', EstadoFactura::Enviada)
            ->whereDate('fecha_vencimiento', '<', now()->toDateString());
    }

    public function estaVencida(): bool
    {
        return $this->estado === EstadoFactura::Enviada
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->isBefore(now()->startOfDay());
    }

    public function diasVencida(): int
    {
        return $this->estaVencida()
            ? (int) $this->fecha_vencimiento?->diffInDays(now()->startOfDay())
            : 0;
    }

    /**
     * Número visible: el oficial si ya se emitió, o "Borrador" si todavía no.
     */
    public function referencia(): string
    {
        return $this->numero ?? __('Draft #:id', ['id' => $this->id]);
    }

    /**
     * Los importes guardados, envueltos en el mismo objeto de valor que los presupuestos.
     */
    public function importes(): Presupuesto
    {
        return new Presupuesto($this->base_centimos, $this->iva_centimos, $this->irpf_centimos);
    }
}
