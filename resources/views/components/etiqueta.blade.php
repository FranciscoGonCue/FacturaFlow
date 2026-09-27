@props(['enum'])

@php
    /** @var \App\Contracts\Etiquetable $enum */
@endphp

{{-- Pinta CUALQUIER enum que implemente Etiquetable como un badge de Flux (polimorfismo). --}}
<flux:badge size="sm" :color="$enum->color()" {{ $attributes }}>{{ $enum->label() }}</flux:badge>
