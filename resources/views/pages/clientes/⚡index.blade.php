<?php

use App\Enums\TipoCliente;
use App\Exceptions\FacturaFlowException;
use App\Models\Cliente;
use App\Services\ClienteService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Página (full-page) con el listado de clientes: búsqueda, filtro por tipo,
 * ordenación por columnas, paginación y borrado con confirmación.
 */
new #[Title('Clients')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $tipo = '';

    #[Url(except: 'nombre')]
    public string $ordenarPor = 'nombre';

    #[Url(except: 'asc')]
    public string $direccion = 'asc';

    /** Id del cliente que se va a borrar (lo elige el botón y lo confirma el modal). */
    public ?int $aEliminar = null;

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'tipo'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Clic en la cabecera de una columna: ordena por ella o invierte el sentido.
     */
    public function ordenar(string $columna): void
    {
        if (! in_array($columna, ['nombre', 'ciudad', 'proyectos_count', 'created_at'], true)) {
            return;
        }

        $this->direccion = $this->ordenarPor === $columna && $this->direccion === 'asc' ? 'desc' : 'asc';
        $this->ordenarPor = $columna;
    }

    public function confirmarBorrado(int $id): void
    {
        $this->aEliminar = $id;
        Flux::modal('borrar-cliente')->show();
    }

    public function eliminar(ClienteService $servicio): void
    {
        $cliente = Cliente::query()->findOrFail($this->aEliminar);
        Gate::authorize('delete', $cliente);

        try {
            $servicio->eliminar($cliente);
            Flux::toast(variant: 'success', text: __('Client deleted.'));
        } catch (FacturaFlowException $e) {
            // Excepción de negocio (tiene proyectos activos o facturas emitidas).
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->aEliminar = null;
        Flux::modal('borrar-cliente')->close();
    }

    #[Computed]
    public function clientes()
    {
        return auth()->user()->clientes()
            ->buscar($this->buscar)
            ->when(TipoCliente::tryFrom($this->tipo), fn ($q, TipoCliente $tipo) => $q->where('tipo', $tipo))
            ->withCount('proyectos')
            ->orderBy($this->ordenarPor, $this->direccion)
            ->paginate(10);
    }
};
?>

<div>
    <x-encabezado :titulo="__('Clients')" :subtitulo="__('The people and companies you work for.')">
        <x-slot:acciones>
            <flux:button variant="primary" icon="plus" :href="route('clientes.create')" wire:navigate>{{ __('New client') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_14rem]">
        <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" :placeholder="__('Search by name, email or city…')" clearable />
        <flux:select wire:model.live="tipo">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (TipoCliente::cases() as $opcion)
                <flux:select.option :value="$opcion->value">{{ $opcion->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->clientes->isEmpty())
        <x-estado-vacio icono="users" :titulo="$buscar || $tipo ? __('No results') : __('You have no clients yet')"
            :texto="$buscar || $tipo ? __('Try another search.') : __('Create your first client to start registering projects.')">
            @unless ($buscar || $tipo)
                <flux:button variant="primary" :href="route('clientes.create')" wire:navigate>{{ __('Create client') }}</flux:button>
            @endunless
        </x-estado-vacio>
    @else
        <flux:table :paginate="$this->clientes">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$ordenarPor === 'nombre'" :direction="$direccion" wire:click="ordenar('nombre')">{{ __('Client') }}</flux:table.column>
                <flux:table.column class="max-md:hidden">{{ __('Type') }}</flux:table.column>
                <flux:table.column class="max-md:hidden" sortable :sorted="$ordenarPor === 'ciudad'" :direction="$direccion" wire:click="ordenar('ciudad')">{{ __('City') }}</flux:table.column>
                <flux:table.column align="end" sortable :sorted="$ordenarPor === 'proyectos_count'" :direction="$direccion" wire:click="ordenar('proyectos_count')">{{ __('Projects') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->clientes as $cliente)
                    <flux:table.row :key="$cliente->id">
                        <flux:table.cell>
                            <a href="{{ route('clientes.show', $cliente) }}" wire:navigate class="flex items-center gap-3">
                                <flux:avatar size="sm" :initials="$cliente->iniciales()" color="auto" :color:seed="$cliente->id" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $cliente->nombre }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $cliente->email }}</span>
                                </span>
                            </a>
                        </flux:table.cell>
                        <flux:table.cell class="max-md:hidden"><x-etiqueta :enum="$cliente->tipo" /></flux:table.cell>
                        <flux:table.cell class="max-md:hidden">{{ $cliente->ciudad ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $cliente->proyectos_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('clientes.show', $cliente)" wire:navigate>{{ __('View') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil-square" :href="route('clientes.edit', $cliente)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmarBorrado({{ $cliente->id }})">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <x-confirmar-borrado nombre="borrar-cliente" accion="eliminar" :titulo="__('Delete this client?')"
        :mensaje="__('Their finished projects and drafts will also be deleted. It cannot be deleted if it has active projects or issued invoices.')" />
</div>
