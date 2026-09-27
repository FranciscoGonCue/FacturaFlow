@php $importes = $factura->importes(); @endphp
<x-mail::message>
# {{ $recordatorio ? __('Payment reminder') : __('Invoice :number', ['number' => $factura->numero]) }}

{{ __('Hello :client,', ['client' => $factura->cliente->nombre]) }}

@if ($recordatorio)
{{ __('We remind you that invoice :number expired on :date and is still pending.', ['number' => $factura->numero, 'date' => $factura->fecha_vencimiento?->format('d/m/Y')]) }}
@else
{{ __('Please find the details of invoice :number for ":concept" below.', ['number' => $factura->numero, 'concept' => $factura->concepto]) }}
@endif

<x-mail::table>
| {{ __('Description') }} | {{ __('Amount') }} |
|:--|--:|
@foreach ($factura->lineas as $linea)
| {{ $linea->descripcion }} | {{ $linea->importe() }} |
@endforeach
| **{{ __('Total') }}** | **{{ $importes->total() }}** |
</x-mail::table>

{{ __('Due date') }}: **{{ $factura->fecha_vencimiento?->format('d/m/Y') }}**

@if ($factura->user->iban)
{{ __('Payment method: bank transfer to') }} **{{ trim(chunk_split($factura->user->iban, 4, ' ')) }}**
@endif

{{ __('Thank you,') }}<br>
{{ $factura->user->name }}
</x-mail::message>
