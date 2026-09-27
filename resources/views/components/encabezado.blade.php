@props(['titulo', 'subtitulo' => null])

{{-- Cabecera común de todas las páginas: título, subtítulo y botones de acción (slot "acciones"). --}}
<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between print:hidden']) }}>
    <div class="min-w-0">
        <flux:heading size="xl" level="1">{{ $titulo }}</flux:heading>
        @if ($subtitulo)
            <flux:text class="mt-1">{{ $subtitulo }}</flux:text>
        @endif
    </div>

    @isset($acciones)
        <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
    @endisset
</div>
