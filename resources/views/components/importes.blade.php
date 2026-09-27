@props(['importes', 'titulo' => null])

@php
    /** @var \App\ValueObjects\Presupuesto $importes */
    use App\Services\CalculadoraPresupuesto;
@endphp

{{-- Desglose económico reutilizable: base + IVA − IRPF = total. Lo usan proyectos y facturas. --}}
<flux:card {{ $attributes->merge(['class' => 'p-5']) }}>
    @if ($titulo)
        <flux:heading class="mb-4 flex items-center gap-2">
            <flux:icon.currency-euro class="size-5 text-indigo-500" /> {{ $titulo }}
        </flux:heading>
    @endif

    <dl class="space-y-2.5 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Taxable base') }}</dt>
            <dd class="font-medium tabular-nums">{{ $importes->base() }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('VAT') }} ({{ round(CalculadoraPresupuesto::IVA * 100) }} %)</dt>
            <dd class="tabular-nums">+ {{ $importes->iva() }}</dd>
        </div>
        <div @class(['flex justify-between gap-4', 'opacity-40' => $importes->irpfCentimos === 0])>
            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Income tax withholding') }} ({{ round(CalculadoraPresupuesto::IRPF * 100) }} %)</dt>
            <dd class="tabular-nums">− {{ $importes->irpf() }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-t border-zinc-200 pt-3 text-base font-semibold dark:border-zinc-700">
            <dt>{{ __('Total') }}</dt>
            <dd class="text-indigo-600 tabular-nums dark:text-indigo-400">{{ $importes->total() }}</dd>
        </div>
    </dl>
</flux:card>
