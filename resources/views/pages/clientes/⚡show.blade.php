<?php

use App\Exceptions\FacturaFlowException;
use App\Models\Cliente;
use App\Services\ClienteService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Ficha de un cliente: datos de contacto, sus proyectos y sus facturas.
 */
new #[Title('Client')] class extends Component
{
    public Cliente $cliente;

    public function mount(Cliente $cliente): void
    {
        // Route Model Binding + Policy: si el cliente no es tuyo → 403.
        Gate::authorize('view', $cliente);
        $this->cliente = $cliente;
    }

    #[Computed]
    public function proyectos()
    {
        return $this->cliente->proyectos()->with(['cliente', 'etiquetas'])->latest('fecha_inicio')->get();
    }

    #[Computed]
    public function facturas()
    {
        return $this->cliente->facturas()->with('cliente')->latest('fecha_emision')->get();
    }

    public function eliminar(ClienteService $servicio): void
    {
        Gate::authorize('delete', $this->cliente);

        try {
            $servicio->eliminar($this->cliente);
        } catch (FacturaFlowException $e) {
            Flux::modal('borrar-cliente')->close();
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Client deleted.'));
        $this->redirectRoute('clientes.index', navigate: true);
    }
};
?>

<div>
    <x-encabezado :titulo="$cliente->nombre" :subtitulo="__('Client since :date', ['date' => $cliente->created_at->translatedFormat('F Y')])">
        <x-slot:acciones>
            <flux:button icon="pencil-square" :href="route('clientes.edit', $cliente)" wire:navigate>{{ __('Edit') }}</flux:button>
            <flux:modal.trigger name="borrar-cliente">
                <flux:button icon="trash" variant="danger">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:acciones>
    </x-encabezado>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="h-fit space-y-5">
            <div class="flex items-center gap-4">
                <flux:avatar size="lg" :initials="$cliente->iniciales()" color="auto" :color:seed="$cliente->id" />
                <div>
                    <x-etiqueta :enum="$cliente->tipo" />
                    <flux:text size="sm" class="mt-1">
                        {{ $cliente->tipo->aplicaRetencionIrpf() ? __('With income tax withholding') : __('No income tax withholding') }}
                    </flux:text>
                </div>
            </div>

            <dl class="space-y-3 text-sm">
                <div class="flex items-center gap-3"><flux:icon.envelope class="size-4 text-zinc-400" /><flux:link href="mailto:{{ $cliente->email }}" class="truncate">{{ $cliente->email }}</flux:link></div>
                @if ($cliente->telefono)<div class="flex items-center gap-3"><flux:icon.phone class="size-4 text-zinc-400" />{{ $cliente->telefono }}</div>@endif
                @if ($cliente->ciudad)<div class="flex items-center gap-3"><flux:icon.map-pin class="size-4 text-zinc-400" />{{ $cliente->ciudad }}</div>@endif
                @if ($cliente->nif)<div class="flex items-center gap-3"><flux:icon.identification class="size-4 text-zinc-400" />{{ $cliente->nif }}</div>@endif
            </dl>

            @if ($cliente->notas)
                <flux:callout icon="information-circle" color="zinc" :text="$cliente->notas" />
            @endif
        </flux:card>

        <div class="space-y-8 lg:col-span-2">
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Projects') }} ({{ $this->proyectos->count() }})</flux:heading>
                    <flux:button size="sm" icon="plus" :href="route('proyectos.create', ['cliente' => $cliente->id])" wire:navigate>{{ __('Project') }}</flux:button>
                </div>

                <div class="space-y-2">
                    @forelse ($this->proyectos as $proyecto)
                        <a wire:key="p-{{ $proyecto->id }}" href="{{ route('proyectos.show', $proyecto) }}" wire:navigate
                            class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-4 transition hover:border-indigo-300 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:hover:border-indigo-500/50">
                            <div class="min-w-0">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $proyecto->nombre }}</p>
                                <p class="text-xs text-zinc-500">{{ $proyecto->horas_estimadas }} h × {{ number_format((float) $proyecto->tarifa_hora, 2, ',', '.') }} €/h</p>
                                <x-etiquetas-proyecto :etiquetas="$proyecto->etiquetas" class="mt-2" />
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold tabular-nums">{{ $proyecto->presupuesto()->base() }}</span>
                                <x-etiqueta :enum="$proyecto->estado" />
                            </div>
                        </a>
                    @empty
                        <x-estado-vacio icono="briefcase" :titulo="__('No projects yet')" :texto="__('Create the first project for this client.')" />
                    @endforelse
                </div>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Invoices') }} ({{ $this->facturas->count() }})</flux:heading>
                    <flux:button size="sm" icon="plus" :href="route('facturas.create', ['cliente' => $cliente->id])" wire:navigate>{{ __('Invoice') }}</flux:button>
                </div>
                @include('partials.mini-lista-facturas', ['facturas' => $this->facturas])
            </section>
        </div>
    </div>

    <x-confirmar-borrado nombre="borrar-cliente" accion="eliminar" :titulo="__('Delete :name?', ['name' => $cliente->nombre])"
        :mensaje="__('Their finished projects and drafts will also be deleted. It cannot be deleted if it has active projects or issued invoices.')" />
</div>
