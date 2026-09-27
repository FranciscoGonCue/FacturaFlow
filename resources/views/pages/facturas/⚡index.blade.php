<?php

use App\Enums\EstadoFactura;
use App\Exceptions\FacturaFlowException;
use App\Models\Factura;
use App\Services\FacturaService;
use App\ValueObjects\Presupuesto;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Listado de facturas: pestañas por estado (con contadores), búsqueda en vivo,
 * cobro rápido y exportación a CSV (se abre en Excel).
 */
new #[Title('Invoices')] class extends Component
{
    use WithPagination;

    /** Pestañas. "vencida" es un filtro calculado, no un estado guardado. */
    public const array PESTANAS = ['todas', 'borrador', 'enviada', 'vencida', 'pagada'];

    #[Url(except: 'todas')]
    public string $pestana = 'todas';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['pestana', 'buscar'], true)) {
            $this->resetPage();
        }
    }

    public function cambiarPestana(string $pestana): void
    {
        $this->pestana = in_array($pestana, self::PESTANAS, true) ? $pestana : 'todas';
        $this->resetPage();
    }

    /**
     * Registrar el cobro sin salir del listado (reutiliza el mismo servicio que la ficha).
     */
    public function cobrar(int $facturaId, FacturaService $servicio): void
    {
        $factura = Factura::query()->findOrFail($facturaId);
        Gate::authorize('pagar', $factura);

        try {
            $servicio->marcarPagada($factura);
            Flux::toast(variant: 'success', text: __('Payment registered: :number', ['number' => $factura->numero]));
        } catch (FacturaFlowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * Descarga un CSV con las facturas de la pestaña y la búsqueda actuales.
     */
    public function exportar(): StreamedResponse
    {
        $facturas = $this->filtrada($this->pestana)->with('cliente')->orderBy('fecha_emision')->get();

        return response()->streamDownload(function () use ($facturas): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM: para que Excel muestre bien las tildes
            fputcsv($salida, [__('Number'), __('Client'), __('Concept'), __('Status'), __('Issue date'), __('Due date'), __('Taxable base'), __('VAT'), __('Withholding'), __('Total')], ';');

            foreach ($facturas as $f) {
                fputcsv($salida, [
                    $f->referencia(),
                    $f->cliente->nombre,
                    $f->concepto,
                    $f->estaVencida() ? __('Overdue') : $f->estado->label(),
                    $f->fecha_emision?->format('d/m/Y'),
                    $f->fecha_vencimiento?->format('d/m/Y'),
                    number_format($f->base_centimos / 100, 2, ',', ''),
                    number_format($f->iva_centimos / 100, 2, ',', ''),
                    number_format($f->irpf_centimos / 100, 2, ',', ''),
                    number_format($f->total_centimos / 100, 2, ',', ''),
                ], ';');
            }

            fclose($salida);
        }, 'facturas-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Consulta base (con la búsqueda) + el filtro de una pestaña.
     */
    private function filtrada(string $pestana)
    {
        $query = auth()->user()->facturas()
            ->when($this->buscar !== '', function ($query): void {
                $patron = '%'.trim($this->buscar).'%';
                $query->where(fn ($q) => $q
                    ->where('numero', 'like', $patron)
                    ->orWhere('concepto', 'like', $patron)
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', $patron)));
            });

        return match ($pestana) {
            'vencida' => $query->vencidas(),
            'borrador', 'enviada', 'pagada' => $query->where('estado', EstadoFactura::from($pestana)),
            default => $query,
        };
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function contadores(): array
    {
        return collect(self::PESTANAS)->mapWithKeys(fn (string $p): array => [$p => $this->filtrada($p)->count()])->all();
    }

    #[Computed]
    public function facturas()
    {
        return $this->filtrada($this->pestana)
            ->with('cliente')
            ->orderByRaw('fecha_emision IS NULL DESC')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('secuencia')
            ->paginate(10);
    }

    /**
     * @return array{facturado: string, pendiente: string}
     */
    #[Computed]
    public function totales(): array
    {
        return [
            'facturado' => Presupuesto::formatear((int) $this->filtrada($this->pestana)->where('estado', '!=', EstadoFactura::Borrador)->sum('total_centimos')),
            'pendiente' => Presupuesto::formatear((int) $this->filtrada($this->pestana)->where('estado', EstadoFactura::Enviada)->sum('total_centimos')),
        ];
    }
};
?>

@php
    $nombres = ['todas' => __('All'), 'borrador' => __('Drafts'), 'enviada' => __('Sent'), 'vencida' => __('Overdue'), 'pagada' => __('Paid')];
@endphp

<div>
    <x-encabezado :titulo="__('Invoices')" :subtitulo="__('Drafts, issued invoices and pending payments.')">
        <x-slot:acciones>
            <flux:button icon="arrow-down-tray" wire:click="exportar">{{ __('Export CSV') }}</flux:button>
            <flux:button variant="primary" icon="plus" :href="route('facturas.create')" wire:navigate>{{ __('New invoice') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:mx-0 lg:px-0" aria-label="{{ __('Filter by status') }}">
            @foreach ($nombres as $clave => $nombre)
                <button type="button" wire:click="cambiarPestana('{{ $clave }}')"
                    @class([
                        'flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-zinc-900 shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-700 dark:text-white dark:ring-zinc-600' => $pestana === $clave,
                        'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $pestana !== $clave,
                    ])
                    @if ($pestana === $clave) aria-current="true" @endif>
                    {{ $nombre }}
                    <flux:badge size="sm" :color="$clave === 'vencida' && $this->contadores['vencida'] > 0 ? 'red' : 'zinc'">{{ $this->contadores[$clave] }}</flux:badge>
                </button>
            @endforeach
        </nav>

        <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" :placeholder="__('Number, concept or client…')" clearable class="lg:w-72" />
    </div>

    <div class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
        <span>{{ __('Invoiced') }}: <strong class="text-zinc-900 dark:text-white">{{ $this->totales['facturado'] }}</strong></span>
        <span>{{ __('Pending payment') }}: <strong class="text-zinc-900 dark:text-white">{{ $this->totales['pendiente'] }}</strong></span>
        <span wire:loading class="text-indigo-600 dark:text-indigo-400">{{ __('Updating…') }}</span>
    </div>

    @if ($this->facturas->isEmpty())
        <x-estado-vacio icono="document-text" :titulo="__('No invoices here')" :texto="__('Create a new invoice or invoice a project directly.')">
            <flux:button variant="primary" :href="route('facturas.create')" wire:navigate>{{ __('New invoice') }}</flux:button>
        </x-estado-vacio>
    @else
        <flux:table :paginate="$this->facturas" wire:loading.class="opacity-60">
            <flux:table.columns>
                <flux:table.column>{{ __('Invoice') }}</flux:table.column>
                <flux:table.column class="max-md:hidden">{{ __('Client') }}</flux:table.column>
                <flux:table.column class="max-lg:hidden">{{ __('Due date') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->facturas as $factura)
                    <flux:table.row :key="$factura->id">
                        <flux:table.cell>
                            <a href="{{ route('facturas.show', $factura) }}" wire:navigate class="block">
                                <span class="block font-medium tabular-nums text-zinc-900 dark:text-white">{{ $factura->referencia() }}</span>
                                <span class="block max-w-56 truncate text-xs text-zinc-500">{{ $factura->concepto }}</span>
                            </a>
                        </flux:table.cell>
                        <flux:table.cell class="max-md:hidden">{{ $factura->cliente->nombre }}</flux:table.cell>
                        <flux:table.cell class="max-lg:hidden">
                            @if ($factura->pagada_en) {{ __('Paid on :date', ['date' => $factura->pagada_en->format('d/m/Y')]) }}
                            @elseif ($factura->fecha_vencimiento) {{ $factura->fecha_vencimiento->format('d/m/Y') }}
                            @else — @endif
                        </flux:table.cell>
                        <flux:table.cell><x-estado-factura :factura="$factura" /></flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold tabular-nums">{{ Presupuesto::formatear($factura->total_centimos) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($factura->estado === EstadoFactura::Enviada)
                                <flux:button size="sm" icon="check" wire:click="cobrar({{ $factura->id }})" wire:loading.attr="disabled" wire:target="cobrar({{ $factura->id }})">{{ __('Paid') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
