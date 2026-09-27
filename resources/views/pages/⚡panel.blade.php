<?php

use App\Enums\EstadoFactura;
use App\Enums\EstadoProyecto;
use App\Models\Factura;
use App\Models\Proyecto;
use App\Support\FacturacionMensual;
use App\ValueObjects\Presupuesto;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Panel del freelancer: métricas, gráfico de 6 meses, cobros pendientes,
 * próximas entregas, mejores clientes y proyectos por estado.
 * Pocas consultas y el resto se calcula en PHP con Colecciones de Laravel.
 */
new #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function proyectos()
    {
        return auth()->user()->proyectos()->with('cliente')->get();
    }

    #[Computed]
    public function facturas()
    {
        return auth()->user()->facturas()->with('cliente')->where('estado', '!=', EstadoFactura::Borrador)->get();
    }

    /**
     * @return array<string, int|string>
     */
    #[Computed]
    public function metricas(): array
    {
        $activos = $this->proyectos->filter(fn (Proyecto $p): bool => $p->estado->esActivo());
        $pendientes = $this->facturas->where('estado', EstadoFactura::Enviada);
        $vencidas = $pendientes->filter(fn (Factura $f): bool => $f->estaVencida());
        $esteAnio = $this->facturas->filter(fn (Factura $f): bool => $f->fecha_emision?->year === now()->year);

        return [
            'facturado' => Presupuesto::formatear((int) $esteAnio->sum('total_centimos')),
            'facturas_anio' => $esteAnio->count(),
            'pendiente' => Presupuesto::formatear((int) $pendientes->sum('total_centimos')),
            'vencidas' => $vencidas->count(),
            'importe_vencido' => Presupuesto::formatear((int) $vencidas->sum('total_centimos')),
            'cartera' => Presupuesto::formatear((int) $activos->sum(fn (Proyecto $p): int => $p->presupuesto()->baseCentimos)),
            'activos' => $activos->count(),
            'retrasados' => $activos->filter(fn (Proyecto $p): bool => $p->estaRetrasado())->count(),
        ];
    }
};
?>

@php
    $m = $this->metricas;
    $cobros = $this->facturas->where('estado', \App\Enums\EstadoFactura::Enviada)->sortBy('fecha_vencimiento')->take(5);
    $entregas = $this->proyectos->filter(fn ($p) => $p->estado->esActivo() && $p->fecha_entrega)->sortBy('fecha_entrega')->take(5);
    $topClientes = $this->facturas->groupBy('cliente_id')
        ->map(fn ($g) => ['cliente' => $g->first()->cliente, 'centimos' => (int) $g->sum('total_centimos')])
        ->sortByDesc('centimos')->take(5);
    $maxCliente = max(1, (int) $topClientes->max('centimos'));
    $totalProyectos = max(1, $this->proyectos->count());
@endphp

<div>
    <x-encabezado :titulo="__('Dashboard')" :subtitulo="__('Hi, :name. This is how your business is going.', ['name' => auth()->user()->name])">
        <x-slot:acciones>
            <flux:button icon="plus" :href="route('proyectos.create')" wire:navigate>{{ __('Project') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('facturas.create')" wire:navigate>{{ __('Invoice') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    @unless (auth()->user()->tieneDatosFiscales())
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-6" :heading="__('Fill in your tax ID and address to be able to issue invoices.')">
            <x-slot name="actions">
                <flux:button size="sm" :href="route('fiscal.edit')" wire:navigate>{{ __('Complete') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica :etiqueta="__('Invoiced this year')" :valor="$m['facturado']" icono="document-text" tono="emerald" :detalle="__(':count invoices issued in :year', ['count' => $m['facturas_anio'], 'year' => now()->year])" />
        <x-metrica :etiqueta="__('Pending payment')" :valor="$m['pendiente']" icono="clock" tono="amber"
            :detalle="$m['vencidas'] > 0 ? __(':count overdue · :amount', ['count' => $m['vencidas'], 'amount' => $m['importe_vencido']]) : __('No overdue invoices')" />
        <x-metrica :etiqueta="__('Active pipeline')" :valor="$m['cartera']" icono="currency-euro" :detalle="__(':count unfinished projects (base)', ['count' => $m['activos']])" />
        <x-metrica :etiqueta="__('Late deliveries')" :valor="$m['retrasados']" icono="exclamation-triangle" :tono="$m['retrasados'] > 0 ? 'red' : 'emerald'"
            :detalle="$m['retrasados'] > 0 ? __('Review the dates as soon as possible') : __('Everything on time')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-grafico-barras :meses="FacturacionMensual::calcular($this->facturas)" :titulo="__('Invoicing over the last 6 months')" class="xl:col-span-2" />

        <flux:card class="p-0">
            <flux:heading class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ __('Pending payments') }}</flux:heading>
            @forelse ($cobros as $factura)
                <a href="{{ route('facturas.show', $factura) }}" wire:navigate class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3 last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $factura->cliente->nombre }}</p>
                        <p class="text-xs text-zinc-500 tabular-nums">{{ $factura->numero }}</p>
                    </div>
                    <div class="text-end">
                        <p class="text-sm font-semibold tabular-nums">{{ Presupuesto::formatear($factura->total_centimos) }}</p>
                        <p @class(['text-xs', 'font-semibold text-red-600 dark:text-red-400' => $factura->estaVencida(), 'text-zinc-500' => ! $factura->estaVencida()])>
                            {{ $factura->estaVencida() ? __('Overdue :days d ago', ['days' => $factura->diasVencida()]) : __('Due :date', ['date' => $factura->fecha_vencimiento?->format('d/m')]) }}
                        </p>
                    </div>
                </a>
            @empty
                <flux:text class="px-5 py-10 text-center">{{ __('Everything collected. 🎉') }}</flux:text>
            @endforelse
        </flux:card>

        <flux:card class="p-0 xl:col-span-2">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <flux:heading>{{ __('Upcoming deliveries') }}</flux:heading>
                <flux:link :href="route('proyectos.index')" wire:navigate class="text-sm">{{ __('See projects') }}</flux:link>
            </div>
            @forelse ($entregas as $proyecto)
                @php $dias = $proyecto->diasParaEntrega(); @endphp
                <a href="{{ route('proyectos.show', $proyecto) }}" wire:navigate class="flex items-center gap-4 border-b border-zinc-100 px-5 py-3.5 last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50">
                    <flux:avatar size="sm" :initials="$proyecto->cliente->iniciales()" color="auto" :color:seed="$proyecto->cliente_id" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $proyecto->nombre }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $proyecto->cliente->nombre }}</p>
                    </div>
                    <div class="text-end">
                        <p @class([
                            'text-xs font-semibold',
                            'text-red-600 dark:text-red-400' => $dias < 0,
                            'text-amber-600 dark:text-amber-400' => $dias >= 0 && $dias <= 7,
                            'text-zinc-500' => $dias > 7,
                        ])>
                            @if ($dias < 0) {{ __(':days d late', ['days' => abs($dias)]) }} @elseif ($dias === 0) {{ __('Today') }} @else {{ __('In :days d', ['days' => $dias]) }} @endif
                        </p>
                        <p class="text-xs text-zinc-400">{{ $proyecto->fecha_entrega->translatedFormat('d M') }}</p>
                    </div>
                </a>
            @empty
                <flux:text class="px-5 py-10 text-center">{{ __('No pending deliveries.') }}</flux:text>
            @endforelse
        </flux:card>

        <div class="space-y-6">
            <flux:card>
                <flux:heading>{{ __('Best clients') }}</flux:heading>
                <flux:text size="sm">{{ __('By invoiced amount') }}</flux:text>
                <ul class="mt-4 space-y-3">
                    @forelse ($topClientes as $fila)
                        <li>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <a href="{{ route('clientes.show', $fila['cliente']) }}" wire:navigate class="truncate hover:underline">{{ $fila['cliente']->nombre }}</a>
                                <span class="shrink-0 font-medium tabular-nums">{{ Presupuesto::formatear($fila['centimos']) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-1.5 rounded-full bg-violet-500" style="width: {{ round($fila['centimos'] / $maxCliente * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li><flux:text size="sm">{{ __('You have not issued any invoices yet.') }}</flux:text></li>
                    @endforelse
                </ul>
            </flux:card>

            <flux:card>
                <flux:heading>{{ __('Projects by status') }}</flux:heading>
                <ul class="mt-4 space-y-3">
                    @foreach (EstadoProyecto::cases() as $estado)
                        @php $n = $this->proyectos->where('estado', $estado)->count(); @endphp
                        <li>
                            <div class="flex items-center justify-between text-sm">
                                <x-etiqueta :enum="$estado" />
                                <span class="font-medium tabular-nums">{{ $n }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ round($n / $totalProyectos * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </flux:card>
        </div>
    </div>
</div>
