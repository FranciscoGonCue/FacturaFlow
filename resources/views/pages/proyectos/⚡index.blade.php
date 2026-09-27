<?php

use App\Enums\EstadoProyecto;
use App\Models\Proyecto;
use App\ValueObjects\Presupuesto;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Listado de proyectos con BÚSQUEDA + VARIOS FILTROS A LA VEZ (requisito del enunciado):
 * texto, estado, cliente, etiqueta y "solo retrasados", más ordenación y paginación.
 * Todo se aplica en vivo, sin recargar la página.
 */
new #[Title('Projects')] class extends Component
{
    use WithPagination;

    // #[Url] guarda cada filtro en la URL: se puede recargar o compartir sin perderlos.
    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $estado = '';

    #[Url(except: '')]
    public string $cliente = '';

    #[Url(except: '')]
    public string $etiqueta = '';

    #[Url(except: false)]
    public bool $soloRetrasados = false;

    #[Url(except: 'entrega')]
    public string $orden = 'entrega';

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'estado', 'cliente', 'etiqueta', 'soloRetrasados', 'orden'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'estado', 'cliente', 'etiqueta', 'soloRetrasados', 'orden');
        $this->resetPage();
    }

    public function cambiarEstado(int $proyectoId, string $nuevoEstado): void
    {
        $proyecto = Proyecto::query()->findOrFail($proyectoId);

        // El botón solo aparece en tus proyectos, pero el servidor SIEMPRE lo vuelve a comprobar.
        Gate::authorize('update', $proyecto);

        $proyecto->update(['estado' => EstadoProyecto::from($nuevoEstado)]);

        Flux::toast(text: __('":name" → :status', ['name' => $proyecto->nombre, 'status' => $proyecto->estado->label()]));
    }

    /**
     * Consulta con TODOS los filtros combinados. Cada ->when() solo se aplica si el filtro tiene valor.
     */
    #[Computed]
    public function consulta()
    {
        return auth()->user()->proyectos()
            ->with(['cliente', 'etiquetas'])
            ->when($this->buscar !== '', function ($query): void {
                $patron = '%'.trim($this->buscar).'%';
                $query->where(fn ($q) => $q
                    ->where('nombre', 'like', $patron)
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', $patron)));
            })
            ->when(EstadoProyecto::tryFrom($this->estado), fn ($query, EstadoProyecto $estado) => $query->where('estado', $estado))
            ->when($this->cliente !== '', fn ($query) => $query->where('cliente_id', (int) $this->cliente))
            // Filtro por la relación muchos a muchos: proyectos que TIENEN esa etiqueta.
            ->when($this->etiqueta !== '', fn ($query) => $query->whereHas('etiquetas', fn ($e) => $e->whereKey((int) $this->etiqueta)))
            ->when($this->soloRetrasados, fn ($query) => $query->activos()->whereDate('fecha_entrega', '<', today()));
    }

    #[Computed]
    public function proyectos()
    {
        $consulta = clone $this->consulta;

        match ($this->orden) {
            'nombre' => $consulta->orderBy('nombre'),
            'recientes' => $consulta->latest(),
            'tarifa' => $consulta->orderByDesc('tarifa_hora'),
            default => $consulta->orderByRaw('fecha_entrega IS NULL')->orderBy('fecha_entrega'),
        };

        return $consulta->paginate(10);
    }

    /**
     * Totales de TODOS los resultados filtrados, no solo de la página actual.
     *
     * @return array{total: int, horas: int, base: string}
     */
    #[Computed]
    public function resumen(): array
    {
        $todos = (clone $this->consulta)->get();

        return [
            'total' => $todos->count(),
            'horas' => (int) $todos->sum('horas_estimadas'),
            'base' => Presupuesto::formatear($todos->sum(fn (Proyecto $p): int => $p->presupuesto()->baseCentimos)),
        ];
    }

    #[Computed]
    public function clientes()
    {
        return auth()->user()->clientes()->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function etiquetas()
    {
        return auth()->user()->etiquetas()->orderBy('nombre')->get();
    }

    public function hayFiltros(): bool
    {
        return $this->buscar !== '' || $this->estado !== '' || $this->cliente !== '' || $this->etiqueta !== '' || $this->soloRetrasados;
    }
};
?>

<div>
    <x-encabezado :titulo="__('Projects')" :subtitulo="__('Search, combine filters and change the status without reloading the page.')">
        <x-slot:acciones>
            <flux:button icon="view-columns" :href="route('proyectos.tablero')" wire:navigate>{{ __('Board') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('proyectos.create')" wire:navigate>{{ __('New project') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    {{-- Filtros --}}
    <flux:card class="mb-4 space-y-3 p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_11rem_13rem_11rem_12rem]">
            <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" :placeholder="__('Search project or client…')" clearable class="md:col-span-2 xl:col-span-1" />

            <flux:select wire:model.live="estado" :aria-label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (EstadoProyecto::cases() as $opcion)
                    <flux:select.option :value="$opcion->value">{{ $opcion->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="cliente" :aria-label="__('Client')">
                <flux:select.option value="">{{ __('All clients') }}</flux:select.option>
                @foreach ($this->clientes as $opcion)
                    <flux:select.option :value="$opcion->id">{{ $opcion->nombre }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="etiqueta" :aria-label="__('Tag')">
                <flux:select.option value="">{{ __('All tags') }}</flux:select.option>
                @foreach ($this->etiquetas as $opcion)
                    <flux:select.option :value="$opcion->id">{{ $opcion->nombre }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="orden" :aria-label="__('Sort')">
                <flux:select.option value="entrega">{{ __('Delivery date') }}</flux:select.option>
                <flux:select.option value="recientes">{{ __('Most recent') }}</flux:select.option>
                <flux:select.option value="nombre">{{ __('Name') }}</flux:select.option>
                <flux:select.option value="tarifa">{{ __('Highest rate') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:switch wire:model.live="soloRetrasados" :label="__('Only overdue deliveries')" align="left" />
            @if ($this->hayFiltros())
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="limpiarFiltros">{{ __('Clear filters') }}</flux:button>
            @endif
        </div>
    </flux:card>

    <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
        <span><strong class="text-zinc-900 dark:text-white">{{ $this->resumen['total'] }}</strong> {{ __('projects') }}</span>
        <span><strong class="text-zinc-900 dark:text-white">{{ $this->resumen['horas'] }} h</strong> {{ __('estimated') }}</span>
        <span><strong class="text-zinc-900 dark:text-white">{{ $this->resumen['base'] }}</strong> {{ __('taxable base') }}</span>
        <span wire:loading class="text-indigo-600 dark:text-indigo-400">{{ __('Updating…') }}</span>
    </div>

    <div wire:loading.class="opacity-60" class="space-y-3 transition-opacity">
        @forelse ($this->proyectos as $proyecto)
            <flux:card wire:key="proyecto-{{ $proyecto->id }}" class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                <a href="{{ route('proyectos.show', $proyecto) }}" wire:navigate class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $proyecto->nombre }}</span>
                        @if ($proyecto->estaRetrasado())
                            <flux:badge size="sm" color="red" icon="exclamation-triangle">{{ __('Overdue') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text size="sm" class="mt-0.5 truncate">
                        {{ $proyecto->cliente->nombre }}
                        @if ($proyecto->fecha_entrega) · {{ __('delivery') }} {{ $proyecto->fecha_entrega->translatedFormat('d M Y') }} @endif
                    </flux:text>
                    <x-etiquetas-proyecto :etiquetas="$proyecto->etiquetas" class="mt-2" />
                </a>

                <div class="flex items-center justify-between gap-4 sm:justify-end">
                    <div class="sm:text-end">
                        <p class="text-sm font-semibold tabular-nums">{{ $proyecto->presupuesto()->base() }}</p>
                        <flux:text size="sm">{{ $proyecto->horas_estimadas }} h · {{ number_format((float) $proyecto->tarifa_hora, 0, ',', '.') }} €/h</flux:text>
                    </div>

                    {{-- Cambio de estado desde la lista: el menú lo abre Flux/Alpine y Livewire guarda --}}
                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="ghost" icon:trailing="chevron-down" :aria-label="__('Change status')">
                            <x-etiqueta :enum="$proyecto->estado" />
                        </flux:button>
                        <flux:menu>
                            @foreach (EstadoProyecto::cases() as $opcion)
                                <flux:menu.item wire:click="cambiarEstado({{ $proyecto->id }}, '{{ $opcion->value }}')" :icon="$proyecto->estado === $opcion ? 'check' : null">
                                    {{ $opcion->label() }}
                                </flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </flux:card>
        @empty
            <x-estado-vacio icono="briefcase" :titulo="__('No projects match')" :texto="__('Change the filters or create a new project.')">
                <flux:button variant="primary" :href="route('proyectos.create')" wire:navigate>{{ __('Create project') }}</flux:button>
            </x-estado-vacio>
        @endforelse
    </div>

    <div class="mt-4">{{ $this->proyectos->links() }}</div>
</div>
