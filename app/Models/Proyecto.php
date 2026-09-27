<?php

namespace App\Models;

use App\Enums\EstadoProyecto;
use App\Services\CalculadoraPresupuesto;
use App\ValueObjects\Presupuesto;
use Database\Factories\ProyectoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $cliente_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property EstadoProyecto $estado
 * @property string $tarifa_hora
 * @property int $horas_estimadas
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_entrega
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cliente $cliente
 * @property-read Collection<int, Etiqueta> $etiquetas
 */
class Proyecto extends Model
{
    /** @use HasFactory<ProyectoFactory> */
    use HasFactory;

    protected $table = 'proyectos';

    /** @var list<string> */
    protected $fillable = [
        'cliente_id',
        'nombre',
        'descripcion',
        'estado',
        'tarifa_hora',
        'horas_estimadas',
        'fecha_inicio',
        'fecha_entrega',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoProyecto::class,
            'tarifa_hora' => 'decimal:2',
            'horas_estimadas' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_entrega' => 'date',
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

    /** @return HasMany<Factura, $this> */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    /**
     * MUCHOS A MUCHOS: un proyecto puede tener varias etiquetas (tabla pivote etiqueta_proyecto).
     *
     * @return BelongsToMany<Etiqueta, $this>
     */
    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'etiqueta_proyecto')->withTimestamps();
    }

    /**
     * Proyectos que todavía no se han completado.
     *
     * @param  Builder<Proyecto>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->whereIn('estado', EstadoProyecto::activos());
    }

    /**
     * El presupuesto no se guarda en la base de datos: se calcula siempre a partir
     * de la tarifa, las horas y el tipo de cliente, así nunca queda desactualizado.
     */
    public function presupuesto(): Presupuesto
    {
        return app(CalculadoraPresupuesto::class)->calcular(
            (float) $this->tarifa_hora,
            $this->horas_estimadas,
            $this->cliente->tipo,
        );
    }

    public function estaRetrasado(): bool
    {
        return $this->estado->esActivo()
            && $this->fecha_entrega !== null
            && $this->fecha_entrega->isPast()
            && ! $this->fecha_entrega->isToday();
    }

    public function diasParaEntrega(): ?int
    {
        return $this->fecha_entrega === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->fecha_entrega, false);
    }
}
