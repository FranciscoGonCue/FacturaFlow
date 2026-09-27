<?php

use App\Enums\Rol;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * ZONA ADMIN: gestión de usuarios (solo accesible con el middleware "admin").
 * Buscar, filtrar por rol y estado, cambiar el rol, bloquear/desbloquear y eliminar cuentas.
 */
new #[Title('Users')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $rol = '';

    #[Url(except: '')]
    public string $estado = '';

    public ?int $aEliminar = null;

    public function updated(string $propiedad): void
    {
        $this->resetPage();
    }

    public function cambiarRol(int $id, string $rol): void
    {
        $usuario = User::query()->findOrFail($id);
        Gate::authorize('update', $usuario);

        // "rol" no es fillable (seguridad): solo se cambia de forma explícita con forceFill.
        $usuario->forceFill(['rol' => Rol::from($rol)])->save();

        Flux::toast(variant: 'success', text: __(':name is now :role.', ['name' => $usuario->name, 'role' => $usuario->rol->label()]));
    }

    public function alternarBloqueo(int $id): void
    {
        $usuario = User::query()->findOrFail($id);
        Gate::authorize('update', $usuario);

        $usuario->forceFill(['activo' => ! $usuario->activo])->save();

        Flux::toast(
            variant: $usuario->activo ? 'success' : 'warning',
            text: $usuario->activo ? __(':name can sign in again.', ['name' => $usuario->name]) : __(':name has been blocked.', ['name' => $usuario->name]),
        );
    }

    public function confirmarBorrado(int $id): void
    {
        $this->aEliminar = $id;
        Flux::modal('borrar-usuario')->show();
    }

    public function eliminar(): void
    {
        $usuario = User::query()->findOrFail($this->aEliminar);
        Gate::authorize('delete', $usuario);

        // Primero facturas y proyectos (sus claves foráneas impiden borrar clientes con datos);
        // el resto (clientes, etiquetas, líneas) cae en cascada. Todo dentro de una transacción.
        DB::transaction(function () use ($usuario): void {
            $usuario->facturas()->delete();
            $usuario->proyectos()->delete();
            $usuario->delete();
        });

        $this->aEliminar = null;
        Flux::modal('borrar-usuario')->close();
        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    #[Computed]
    public function usuarios()
    {
        return User::query()
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$this->buscar.'%')
                ->orWhere('email', 'like', '%'.$this->buscar.'%')))
            ->when(Rol::tryFrom($this->rol), fn ($q, Rol $rol) => $q->where('rol', $rol))
            ->when($this->estado !== '', fn ($q) => $q->where('activo', $this->estado === 'activo'))
            ->withCount(['clientes', 'proyectos', 'facturas'])
            ->orderBy('name')
            ->paginate(10);
    }
};
?>

<div>
    <x-encabezado :titulo="__('Users')" :subtitulo="__('Manage roles and access for every account.')" />

    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_12rem_12rem]">
        <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" :placeholder="__('Search by name or email…')" clearable />
        <flux:select wire:model.live="rol" :aria-label="__('Role')">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach (Rol::cases() as $opcion)
                <flux:select.option :value="$opcion->value">{{ $opcion->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="estado" :aria-label="__('Status')">
            <flux:select.option value="">{{ __('All accounts') }}</flux:select.option>
            <flux:select.option value="activo">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="bloqueado">{{ __('Blocked') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->usuarios">
        <flux:table.columns>
            <flux:table.column>{{ __('User') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Status') }}</flux:table.column>
            <flux:table.column align="end" class="max-lg:hidden">{{ __('Clients') }}</flux:table.column>
            <flux:table.column align="end" class="max-lg:hidden">{{ __('Invoices') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->usuarios as $usuario)
                <flux:table.row :key="$usuario->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :initials="$usuario->initials()" color="auto" :color:seed="$usuario->id" />
                            <div class="min-w-0">
                                <p class="truncate font-medium text-zinc-900 dark:text-white">
                                    {{ $usuario->name }} @if ($usuario->is(auth()->user()))<span class="text-xs text-zinc-500">({{ __('you') }})</span>@endif
                                </p>
                                <p class="truncate text-xs text-zinc-500">{{ $usuario->email }}</p>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell><x-etiqueta :enum="$usuario->rol" /></flux:table.cell>
                    <flux:table.cell class="max-md:hidden">
                        <flux:badge size="sm" :color="$usuario->activo ? 'emerald' : 'red'">{{ $usuario->activo ? __('Active') : __('Blocked') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="max-lg:hidden tabular-nums">{{ $usuario->clientes_count }}</flux:table.cell>
                    <flux:table.cell align="end" class="max-lg:hidden tabular-nums">{{ $usuario->facturas_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $usuario)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                                <flux:menu>
                                    @foreach (Rol::cases() as $opcion)
                                        <flux:menu.item wire:click="cambiarRol({{ $usuario->id }}, '{{ $opcion->value }}')" :icon="$usuario->rol === $opcion ? 'check' : 'shield-check'">
                                            {{ __('Make :role', ['role' => $opcion->label()]) }}
                                        </flux:menu.item>
                                    @endforeach
                                    <flux:menu.separator />
                                    <flux:menu.item wire:click="alternarBloqueo({{ $usuario->id }})" :icon="$usuario->activo ? 'lock-closed' : 'lock-open'">
                                        {{ $usuario->activo ? __('Block account') : __('Unblock account') }}
                                    </flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="confirmarBorrado({{ $usuario->id }})">{{ __('Delete user') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <x-confirmar-borrado nombre="borrar-usuario" accion="eliminar" :titulo="__('Delete this user?')"
        :mensaje="__('All their clients, projects, invoices and tags will be deleted. This cannot be undone.')" />
</div>
