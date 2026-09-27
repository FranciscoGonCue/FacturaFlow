<?php

use App\Enums\EstadoFactura;
use App\Exceptions\FacturaFlowException;
use App\Mail\FacturaEnviada;
use App\Models\Factura;
use App\Services\FacturaService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Ficha de una factura. Según su estado muestra unas acciones u otras:
 *  Borrador → editar, eliminar, EMITIR (recibe número correlativo).
 *  Enviada  → marcar como pagada, enviar por email.
 *  Todas    → imprimir / guardar como PDF (Alpine: window.print()).
 */
new #[Title('Invoice')] class extends Component
{
    public Factura $factura;

    public function mount(Factura $factura): void
    {
        Gate::authorize('view', $factura);
        $this->factura = $factura;
    }

    public function emitir(FacturaService $servicio): void
    {
        Gate::authorize('emitir', $this->factura);

        if (! auth()->user()->tieneDatosFiscales()) {
            Flux::toast(variant: 'warning', text: __('Before issuing your first invoice, fill in your tax ID and address.'));
            $this->redirectRoute('fiscal.edit', navigate: true);

            return;
        }

        try {
            $servicio->emitir($this->factura);
            Flux::toast(variant: 'success', text: __('Invoice :number issued. Due on :date.', [
                'number' => $this->factura->numero,
                'date' => $this->factura->fecha_vencimiento?->format('d/m/Y'),
            ]));
        } catch (FacturaFlowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function pagar(FacturaService $servicio): void
    {
        Gate::authorize('pagar', $this->factura);

        try {
            $servicio->marcarPagada($this->factura);
            Flux::toast(variant: 'success', text: __('Payment registered: :number', ['number' => $this->factura->numero]));
        } catch (FacturaFlowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * Envía la factura al email del cliente (Laravel Mailable).
     * En local, MAIL_MAILER=log: el correo se escribe en storage/logs/laravel.log.
     */
    public function enviarEmail(): void
    {
        Gate::authorize('view', $this->factura);
        abort_if($this->factura->estado === EstadoFactura::Borrador, 403);

        Mail::to($this->factura->cliente->email)->send(new FacturaEnviada($this->factura));

        Flux::toast(variant: 'success', text: __('Invoice sent to :email.', ['email' => $this->factura->cliente->email]));
    }

    public function eliminar(): void
    {
        Gate::authorize('delete', $this->factura);
        $this->factura->delete();

        Flux::toast(variant: 'success', text: __('Draft deleted.'));
        $this->redirectRoute('facturas.index', navigate: true);
    }

};
?>

@php
    $factura->loadMissing(['cliente', 'proyecto', 'lineas', 'user']);
    $importes = $factura->importes();
    $emisor = $factura->user;
@endphp

<div>
    <x-encabezado :titulo="$factura->referencia()" :subtitulo="$factura->cliente->nombre.' · '.$factura->concepto">
        <x-slot:acciones>
            {{-- Alpine: imprimir o "Guardar como PDF" desde el propio navegador --}}
            <flux:button icon="printer" x-data x-on:click="window.print()">{{ __('Print / PDF') }}</flux:button>

            @can('update', $factura)
                <flux:button icon="pencil-square" :href="route('facturas.edit', $factura)" wire:navigate>{{ __('Edit') }}</flux:button>
                <flux:modal.trigger name="borrar-factura">
                    <flux:button icon="trash" variant="danger" :aria-label="__('Delete draft')" />
                </flux:modal.trigger>
            @endcan

            @can('emitir', $factura)
                <flux:button variant="primary" icon="paper-airplane" wire:click="emitir">{{ __('Issue invoice') }}</flux:button>
            @endcan

            @if ($factura->estado !== EstadoFactura::Borrador)
                <flux:button icon="envelope" wire:click="enviarEmail">{{ __('Send by email') }}</flux:button>
            @endif

            @can('pagar', $factura)
                <flux:button variant="primary" color="emerald" icon="check" wire:click="pagar">{{ __('Mark as paid') }}</flux:button>
            @endcan
        </x-slot:acciones>
    </x-encabezado>

    {{-- Aviso según el estado (no se imprime) --}}
    <div class="mb-6 print:hidden">
        @if ($factura->estado === EstadoFactura::Borrador)
            <flux:callout icon="pencil-square" color="zinc" :heading="__('This is a draft')"
                :text="__('It has no number yet and you can still change it. When you issue it, it will get the next correlative number and be locked.')" />
        @elseif ($factura->estaVencida())
            <flux:callout icon="exclamation-triangle" color="red" :heading="__('Overdue :days days ago', ['days' => $factura->diasVencida()])"
                :text="__('It may be a good moment to remind :client.', ['client' => $factura->cliente->nombre])" />
        @elseif ($factura->estado === EstadoFactura::Pagada)
            <flux:callout icon="check-circle" color="emerald" :heading="__('Paid on :date', ['date' => $factura->pagada_en?->translatedFormat('d F Y')])" />
        @endif
    </div>

    {{-- El documento: se ve igual en pantalla y al imprimir --}}
    <article class="mx-auto max-w-4xl rounded-xl border border-zinc-200 bg-white p-6 text-zinc-900 sm:p-10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 print:max-w-none print:border-0 print:p-0">
        <header class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @if ($emisor->logoUrl())
                    <img src="{{ $emisor->logoUrl() }}" alt="{{ __('Logo') }}" class="h-12 w-auto object-contain">
                @else
                    <x-app-logo />
                @endif
                <div class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $emisor->name }}</p>
                    @if ($emisor->nif)<p>{{ __('Tax ID') }}: {{ $emisor->nif }}</p>@endif
                    @if ($emisor->direccion)<p>{{ $emisor->direccion }}</p>@endif
                    @if ($emisor->ciudad)<p>{{ $emisor->ciudad }}</p>@endif
                    <p>{{ $emisor->email }}</p>
                </div>
            </div>
            <div class="sm:text-end">
                <p class="text-xs font-semibold tracking-widest text-zinc-500 uppercase">{{ __('Invoice') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $factura->numero ?? __('DRAFT') }}</p>
                <div class="mt-2 print:hidden"><x-estado-factura :factura="$factura" /></div>
                <dl class="mt-3 space-y-0.5 text-sm text-zinc-600 dark:text-zinc-400">
                    <div><dt class="inline">{{ __('Issue date') }}:</dt> <dd class="inline font-medium text-zinc-900 dark:text-white">{{ $factura->fecha_emision?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="inline">{{ __('Due date') }}:</dt> <dd class="inline font-medium text-zinc-900 dark:text-white">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? __(':days days after issue', ['days' => $factura->dias_pago]) }}</dd></div>
                </dl>
            </div>
        </header>

        <section class="mt-8 rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800/60 print:bg-zinc-100">
            <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ __('Bill to') }}</p>
            <p class="mt-1 font-semibold">{{ $factura->cliente->nombre }}</p>
            <p class="text-zinc-600 dark:text-zinc-400">
                @if ($factura->cliente->nif) {{ __('Tax ID') }}: {{ $factura->cliente->nif }} · @endif
                {{ $factura->cliente->email }}
                @if ($factura->cliente->ciudad) · {{ $factura->cliente->ciudad }} @endif
            </p>
        </section>

        <p class="mt-6 text-sm">
            <span class="text-zinc-500">{{ __('Concept') }}:</span> <span class="font-medium">{{ $factura->concepto }}</span>
            @if ($factura->proyecto)
                <span class="text-zinc-500">· {{ __('Project') }}</span>
                <a href="{{ route('proyectos.show', $factura->proyecto) }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400 print:text-zinc-900">{{ $factura->proyecto->nombre }}</a>
            @endif
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[32rem] text-sm">
                <thead class="border-b border-zinc-200 text-start text-xs text-zinc-500 uppercase dark:border-zinc-700">
                    <tr>
                        <th class="py-2 text-start font-medium">{{ __('Description') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Quantity') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Price') }}</th>
                        <th class="py-2 text-end font-medium">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($factura->lineas as $linea)
                        <tr>
                            <td class="py-3 pe-4">{{ $linea->descripcion }}</td>
                            <td class="py-3 text-end tabular-nums">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 2, ',', '.'), '0'), ',') }}</td>
                            <td class="py-3 text-end tabular-nums">{{ $linea->precio() }}</td>
                            <td class="py-3 text-end font-medium tabular-nums">{{ $linea->importe() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <dl class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Taxable base') }}</dt><dd class="tabular-nums">{{ $importes->base() }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('VAT') }} (21 %)</dt><dd class="tabular-nums">+ {{ $importes->iva() }}</dd></div>
                @if ($importes->irpfCentimos > 0)
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Income tax withholding') }} (15 %)</dt><dd class="tabular-nums">− {{ $importes->irpf() }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ $importes->total() }}</dd></div>
            </dl>
        </div>

        @if ($emisor->iban || $factura->notas)
            <footer class="mt-10 border-t border-zinc-200 pt-4 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                @if ($emisor->iban)
                    {{-- Alpine: copiar el IBAN al portapapeles --}}
                    <p x-data="{ copiado: false }">
                        {{ __('Payment method: bank transfer to') }}
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ trim(chunk_split($emisor->iban, 4, ' ')) }}</span>
                        <button type="button" class="ms-1 text-indigo-600 hover:underline dark:text-indigo-400 print:hidden"
                            x-on:click="navigator.clipboard.writeText('{{ $emisor->iban }}'); copiado = true; setTimeout(() => copiado = false, 2000)"
                            x-text="copiado ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</button>
                    </p>
                @endif
                @if ($factura->notas)<p class="mt-1">{{ $factura->notas }}</p>@endif
            </footer>
        @endif
    </article>

    <x-confirmar-borrado nombre="borrar-factura" accion="eliminar" :titulo="__('Delete this draft?')"
        :mensaje="__('Only drafts can be deleted. Issued invoices are always kept.')" />
</div>
