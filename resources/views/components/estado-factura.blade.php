@props(['factura'])

@php
    /** @var \App\Models\Factura $factura */
@endphp

{{-- "Vencida" no es un estado guardado: se calcula comparando la fecha de vencimiento con hoy. --}}
@if ($factura->estaVencida())
    <flux:badge size="sm" color="red" icon="exclamation-triangle" {{ $attributes }}>
        {{ __('Overdue') }} · {{ $factura->diasVencida() }} {{ __('d') }}
    </flux:badge>
@else
    <x-etiqueta :enum="$factura->estado" {{ $attributes }} />
@endif
