{{-- Lista compacta de facturas, reutilizada en la ficha del cliente y en la del proyecto. --}}
<div class="space-y-2">
    @forelse ($facturas as $factura)
        <a wire:key="f-{{ $factura->id }}" href="{{ route('facturas.show', $factura) }}" wire:navigate
            class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 transition hover:border-indigo-300 dark:border-zinc-700 dark:hover:border-indigo-500/50">
            <div class="min-w-0">
                <p class="text-sm font-medium tabular-nums text-zinc-900 dark:text-white">{{ $factura->referencia() }}</p>
                <p class="truncate text-xs text-zinc-500">{{ $factura->concepto }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold tabular-nums">{{ \App\ValueObjects\Presupuesto::formatear($factura->total_centimos) }}</span>
                <x-estado-factura :factura="$factura" />
            </div>
        </a>
    @empty
        <x-estado-vacio icono="document-text" :titulo="__('No invoices yet')" />
    @endforelse
</div>
