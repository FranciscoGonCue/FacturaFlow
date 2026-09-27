<?php

namespace App\Models;

use App\Enums\EstadoProyecto;
use App\Enums\TipoCliente;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property TipoCliente $tipo
 * @property string $nombre
 * @property string $email
 * @property string|null $telefono
 * @property string|null $nif
 * @property string|null $ciudad
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $table = 'clientes';

    /** @var list<string> */
    protected $fillable = [
        'tipo',
        'nombre',
        'email',
        'telefono',
        'nif',
        'ciudad',
        'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCliente::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Proyecto, $this> */
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }

    /** @return HasMany<Factura, $this> */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    /**
     * Búsqueda por nombre, email o ciudad: Cliente::buscar('madrid')->get().
     *
     * @param  Builder<Cliente>  $query
     */
    public function scopeBuscar(Builder $query, ?string $texto): void
    {
        if (blank($texto)) {
            return;
        }

        $patron = '%'.trim($texto).'%';

        // Agrupamos los OR entre paréntesis para no "romper" otros where de la consulta.
        $query->where(function (Builder $q) use ($patron): void {
            $q->where('nombre', 'like', $patron)
                ->orWhere('email', 'like', $patron)
                ->orWhere('ciudad', 'like', $patron);
        });
    }

    public function iniciales(): string
    {
        $palabras = preg_split('/\s+/', trim($this->nombre)) ?: [];

        return mb_strtoupper(implode('', array_map(
            fn (string $palabra): string => mb_substr($palabra, 0, 1),
            array_slice($palabras, 0, 2),
        )));
    }

    public function tieneProyectosActivos(): bool
    {
        return $this->proyectos()->whereIn('estado', EstadoProyecto::activos())->exists();
    }
}
