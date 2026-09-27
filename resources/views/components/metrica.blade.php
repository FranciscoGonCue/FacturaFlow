@props(['etiqueta', 'valor', 'icono', 'detalle' => null, 'tono' => 'indigo'])

@php
    $tonos = [
        'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
    ];
@endphp

{{-- Tarjeta de métrica del panel. --}}
<flux:card class="p-5">
    <div class="flex items-center justify-between gap-3">
        <flux:text class="font-medium">{{ $etiqueta }}</flux:text>
        <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $tonos[$tono] ?? $tonos['indigo'] }}">
            <flux:icon :name="$icono" variant="outline" class="size-5" />
        </span>
    </div>
    <p class="mt-3 text-2xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white">{{ $valor }}</p>
    @if ($detalle)
        <flux:text size="sm" class="mt-1">{{ $detalle }}</flux:text>
    @endif
</flux:card>
