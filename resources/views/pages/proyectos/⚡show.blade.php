<?php

use App\Models\Proyecto;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Ficha de un proyecto: datos, presupuesto (calculado en PHP), etiquetas y facturas.
 */
new #[Title('Project')] class extends Component
{
    public Proyecto $proyecto;

    public function mount(Proyecto $proyecto): void
    {
        Gate::authorize('view', $proyecto);
        $this->proyecto = $proyecto->load(['cliente', 'etiquetas', 'facturas.cliente']);
    }

    public function eliminar(): void
    {
        Gate::authorize('delete', $this->proyecto);
        $this->proyecto->delete();

        Flux::toast(variant: 'success', text: __('Project deleted.'));
        $this->redirectRoute('proyectos.index', navigate: true);
    }
};
?>

<div>
    <x-encabezado :titulo="$proyecto->nombre" :subtitulo="$proyecto->cliente->nombre">
        <x-slot:acciones>
            <flux:button variant="primary" icon="document-text" :href="route('facturas.create', ['proyecto' => $proyecto->id])" wire:navigate>{{ __('Invoice it') }}</flux:button>
            <flux:button icon="pencil-square" :href="route('proyectos.edit', $proyecto)" wire:navigate>{{ __('Edit') }}</flux:button>
            <flux:modal.trigger name="borrar-proyecto">
                <flux:button icon="trash" variant="danger" :aria-label="__('Delete')" />
            </flux:modal.trigger>
        </x-slot:acciones>
    </x-encabezado>

    @if ($proyecto->estaRetrasado())
        <flux:callout icon="exclamation-triangle" color="red" class="mb-6"
            :heading="__('The delivery date passed :days days ago.', ['days' => abs((int) $proyecto->diasParaEntrega())])" />
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-6">
                <div class="flex flex-wrap items-center gap-2">
                    <x-etiqueta :enum="$proyecto->estado" />
                    <flux:link :href="route('clientes.show', $proyecto->cliente)" wire:navigate class="text-sm">{{ $proyecto->cliente->nombre }}</flux:link>
                    <x-etiqueta :enum="$proyecto->cliente->tipo" />
                </div>

                <x-etiquetas-proyecto :etiquetas="$proyecto->etiquetas" />

                <dl class="grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['icono' => 'clock', 'etiqueta' => __('Estimated hours'), 'valor' => $proyecto->horas_estimadas.' h'],
                        ['icono' => 'currency-euro', 'etiqueta' => __('Rate'), 'valor' => number_format((float) $proyecto->tarifa_hora, 2, ',', '.').' €/h'],
                        ['icono' => 'calendar', 'etiqueta' => __('Delivery'), 'valor' => $proyecto->fecha_entrega?->translatedFormat('d M Y') ?? __('No date')],
                    ] as $dato)
                        <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800/60">
                            <dt class="flex items-center gap-2 text-xs text-zinc-500"><flux:icon :name="$dato['icono']" class="size-4" /> {{ $dato['etiqueta'] }}</dt>
                            <dd class="mt-1 font-semibold">{{ $dato['valor'] }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div>
                    <flux:heading size="sm">{{ __('Description') }}</flux:heading>
                    <flux:text class="mt-2 whitespace-pre-line">{{ $proyecto->descripcion ?: __('No description.') }}</flux:text>
                </div>

                @if ($proyecto->fecha_inicio)
                    <flux:text size="sm">{{ __('Started on :date', ['date' => $proyecto->fecha_inicio->translatedFormat('d F Y')]) }}</flux:text>
                @endif
            </flux:card>

            <section>
                <flux:heading size="lg" class="mb-3">{{ __('Invoices for this project') }}</flux:heading>
                @include('partials.mini-lista-facturas', ['facturas' => $proyecto->facturas])
            </section>
        </div>

        <x-importes :importes="$proyecto->presupuesto()" :titulo="__('Quote')" class="h-fit" />
    </div>

    <x-confirmar-borrado nombre="borrar-proyecto" accion="eliminar" :titulo="__('Delete this project?')"
        :mensaje="__('«:name» will be deleted permanently. Its invoices are kept.', ['name' => $proyecto->nombre])" />
</div>
