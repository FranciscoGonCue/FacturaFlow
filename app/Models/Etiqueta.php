<?php

namespace App\Models;

use Database\Factories\EtiquetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Etiqueta libre para clasificar proyectos ("Urgente", "Web", "Mantenimiento"…).
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Etiqueta extends Model
{
    /** @use HasFactory<EtiquetaFactory> */
    use HasFactory;

    /** Colores permitidos (los de <flux:badge>). */
    public const array COLORES = ['zinc', 'red', 'orange', 'amber', 'lime', 'emerald', 'teal', 'sky', 'indigo', 'violet', 'pink'];

    protected $table = 'etiquetas';

    /** @var list<string> */
    protected $fillable = ['nombre', 'color'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * MUCHOS A MUCHOS: una etiqueta puede estar en muchos proyectos.
     * withTimestamps() rellena created_at/updated_at de la tabla pivote.
     *
     * @return BelongsToMany<Proyecto, $this>
     */
    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'etiqueta_proyecto')->withTimestamps();
    }
}
