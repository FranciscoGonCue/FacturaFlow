<?php

use App\Enums\EstadoProyecto;
use App\Models\Proyecto;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Tablero Kanban: una columna por estado. Se ARRASTRA una tarjeta a otra columna
 * para cambiar el estado del proyecto.
 *  - Alpine gestiona el arrastrar y soltar en el navegador (eventos dragstart / drop).
 *  - Al soltar, Alpine llama a $wire.mover(...) y Livewire guarda en la base de datos.
 */
new #[Title('Board')] class extends Component
{
    public function mover(int $proyectoId, string $nuevoEstado): void
    {
        $proyecto = Proyecto::query()->findOrFail($proyectoId);
        Gate::authorize('update', $proyecto);

        $estado = EstadoProyecto::from($nuevoEstado);

        if ($proyecto->estado === $estado) {
            return;
        }

        $proyecto->update(['estado' => $estado]);
        unset($this->columnas);

        Flux::toast(text: __('":name" → :status', ['name' => $proyecto->nombre, 'status' => $estado->label()]));
    }

    /**
     * Los proyectos agrupados por estado (una colección por columna).
     *
     * @return array<string, \Illuminate\Support\Collection<int, Proyecto>>
     */
    #[Computed]
    public function columnas(): array
    {
        $proyectos = auth()->user()->proyectos()->with(['cliente', 'etiquetas'])->orderBy('fecha_entrega')->get();

        return collect(EstadoProyecto::cases())
            ->mapWithKeys(fn (EstadoProyecto $estado): array => [$estado->value => $proyectos->where('estado', $estado)->values()])
            ->all();
    }
};
?>

<div>
    <x-encabezado :titulo="__('Board')" :subtitulo="__('Drag a project to another column to change its status.')">
        <x-slot:acciones>
            <flux:button icon="list-bullet" :href="route('proyectos.index')" wire:navigate>{{ __('List view') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach (EstadoProyecto::cases() as $estado)
            {{-- Alpine: "sobre" se activa mientras arrastras algo encima de esta columna --}}
            <section wire:key="col-{{ $estado->value }}"
                x-data="{ sobre: false }"
                x-on:dragover.prevent="sobre = true"
                x-on:dragleave="sobre = false"
                x-on:drop.prevent="sobre = false; $wire.mover(Number($event.dataTransfer.getData('text/plain')), '{{ $estado->value }}')"
                :class="sobre ? 'ring-2 ring-indigo-500 bg-indigo-50/60 dark:bg-indigo-500/10' : ''"
                class="flex min-h-64 flex-col rounded-xl bg-zinc-100/70 p-3 transition dark:bg-zinc-800/50">
                <header class="mb-3 flex items-center justify-between px-1">
                    <x-etiqueta :enum="$estado" />
                    <span class="text-xs font-medium text-zinc-500 tabular-nums">{{ $this->columnas[$estado->value]->count() }}</span>
                </header>

                <div class="flex flex-1 flex-col gap-2">
                    @forelse ($this->columnas[$estado->value] as $proyecto)
                        <article wire:key="card-{{ $proyecto->id }}"
                            draggable="true"
                            x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $proyecto->id }}'); $el.classList.add('opacity-50')"
                            x-on:dragend="$el.classList.remove('opacity-50')"
                            class="cursor-grab rounded-lg border border-zinc-200 bg-white p-3 shadow-xs active:cursor-grabbing dark:border-zinc-700 dark:bg-zinc-900">
                            <a href="{{ route('proyectos.show', $proyecto) }}" wire:navigate class="text-sm font-medium text-zinc-900 hover:underline dark:text-white">{{ $proyecto->nombre }}</a>
                            <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $proyecto->cliente->nombre }}</p>
                            <x-etiquetas-proyecto :etiquetas="$proyecto->etiquetas" class="mt-2" />
                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span class="font-semibold tabular-nums">{{ $proyecto->presupuesto()->base() }}</span>
                                @if ($proyecto->fecha_entrega)
                                    <span @class(['text-red-600 dark:text-red-400 font-semibold' => $proyecto->estaRetrasado(), 'text-zinc-500' => ! $proyecto->estaRetrasado()])>
                                        {{ $proyecto->fecha_entrega->translatedFormat('d M') }}
                                    </span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="grid flex-1 place-items-center rounded-lg border border-dashed border-zinc-300 p-4 text-center text-xs text-zinc-400 dark:border-zinc-700">{{ __('Drop projects here') }}</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
