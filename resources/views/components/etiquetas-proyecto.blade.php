@props(['etiquetas'])

{{-- Lista de etiquetas de un proyecto (relación muchos a muchos). --}}
@if ($etiquetas->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1']) }}>
        @foreach ($etiquetas as $etiqueta)
            <flux:badge size="sm" :color="$etiqueta->color" icon="tag">{{ $etiqueta->nombre }}</flux:badge>
        @endforeach
    </div>
@endif
