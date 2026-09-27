@props(['meses', 'titulo'])

{{--
    Gráfico de barras hecho SOLO con Tailwind (la altura de cada barra es un %).
    Recibe una lista de ['etiqueta' => 'Sep', 'texto' => '1.234,56 €', 'porcentaje' => 80].
    Alpine muestra el importe del mes sobre el que pasas el ratón.
--}}
<flux:card {{ $attributes }} x-data="{ activo: null }">
    <div class="flex items-center justify-between gap-3">
        <flux:heading>{{ $titulo }}</flux:heading>
        <flux:text size="sm" class="tabular-nums" x-show="activo !== null" x-cloak x-text="activo"></flux:text>
    </div>
    <div class="mt-6 flex h-48 items-end gap-3 sm:gap-5" role="img" aria-label="{{ $titulo }}">
        @foreach ($meses as $mes)
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-2"
                x-on:mouseenter="activo = @js($mes['etiqueta'].': '.$mes['texto'])" x-on:mouseleave="activo = null">
                <div class="w-full rounded-t-md bg-gradient-to-t from-indigo-600 to-violet-400 transition-all hover:from-indigo-500"
                    style="height: {{ max(2, $mes['porcentaje']) }}%" title="{{ $mes['etiqueta'] }}: {{ $mes['texto'] }}"></div>
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $mes['etiqueta'] }}</span>
            </div>
        @endforeach
    </div>
</flux:card>
