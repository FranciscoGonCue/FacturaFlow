<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Rol $rol
 * @property bool $activo
 * @property string|null $nif
 * @property string|null $direccion
 * @property string|null $ciudad
 * @property string|null $iban
 * @property string|null $logo_path
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'nif', 'direccion', 'ciudad', 'iban', 'logo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Valores por defecto al crear un usuario (coinciden con los de la migración).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rol' => 'freelancer',
        'activo' => true,
    ];

    /**
     * "rol" y "activo" NO están en #[Fillable]: así nadie puede hacerse admin
     * añadiendo un campo oculto al formulario de registro. Solo los cambia el admin (forceFill).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Cliente, $this> */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
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

    /** @return HasMany<Etiqueta, $this> */
    public function etiquetas(): HasMany
    {
        return $this->hasMany(Etiqueta::class);
    }

    public function esAdministrador(): bool
    {
        return $this->rol === Rol::Administrador;
    }

    /**
     * ¿Ha rellenado los datos que la ley exige al emisor de una factura?
     */
    public function tieneDatosFiscales(): bool
    {
        return filled($this->nif) && filled($this->direccion);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
