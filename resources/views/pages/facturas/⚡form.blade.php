<?php

use App\Enums\TipoCliente;
use App\Models\Factura;
use App\Services\CalculadoraPresupuesto;
use App\Services\FacturaService;
use App\ValueObjects\Presupuesto;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Editor de facturas (crear y editar BORRADORES).
 * Las líneas son un array de Livewire: añadir y quitar se hace con wire:click,
 * y los totales se recalculan en el servidor mientras escribes.
 */
new #[Title('Invoice')] class extends Component
{
    public ?Factura $factura = null;

    public string $cliente_id = '';

    public string $proyecto_id = '';

    public string $concepto = '';

    public string $dias_pago = '30';

    public string $notas = '';

    /** @var list<array{descripcion: string, cantidad: string, precio: string}> */
    public array $lineas = [];

    public function mount(?Factura $factura = null): void
    {
        if ($factura?->exists) {
            // Solo se editan borradores (la Policy lo comprueba).
            Gate::authorize('update', $factura);
            $this->factura = $factura;
            $this->fill([
                'cliente_id' => (string) $factura->cliente_id,
                'proyecto_id' => (string) $factura->proyecto_id,
                'concepto' => $factura->concepto,
                'dias_pago' => (string) $factura->dias_pago,
                'notas' => (string) $factura->notas,
            ]);
            $this->lineas = $factura->lineas->map(fn ($l): array => [
                'descripcion' => $l->descripcion,
                'cantidad' => (string) (float) $l->cantidad,
                'precio' => (string) ($l->precio_centimos / 100),
            ])->all();

            return;
        }

        $this->lineas = [['descripcion' => '', 'cantidad' => '1', 'precio' => '0']];

        // Facturar un proyecto: ?proyecto=ID rellena cliente, concepto y una línea con horas × tarifa.
        $proyecto = request()->integer('proyecto') ? auth()->user()->proyectos()->find(request()->integer('proyecto')) : null;

        if ($proyecto) {
            $this->cliente_id = (string) $proyecto->cliente_id;
            $this->proyecto_id = (string) $proyecto->id;
            $this->concepto = $proyecto->nombre;
            $this->lineas = [[
                'descripcion' => __('Development: :name', ['name' => $proyecto->nombre]),
                'cantidad' => (string) $proyecto->horas_estimadas,
                'precio' => (string) (float) $proyecto->tarifa_hora,
            ]];
        } elseif (request()->integer('cliente')) {
            $this->cliente_id = (string) request()->integer('cliente');
        }
    }

    public function anadirLinea(): void
    {
        $this->lineas[] = ['descripcion' => '', 'cantidad' => '1', 'precio' => '0'];
    }

    public function quitarLinea(int $indice): void
    {
        if (count($this->lineas) > 1) {
            unset($this->lineas[$indice]);
            $this->lineas = array_values($this->lineas);
        }
    }

    /**
     * Al cambiar de cliente, si el proyecto elegido no es suyo, se quita.
     */
    public function updatedClienteId(): void
    {
        if (! $this->proyectosDelCliente->contains('id', (int) $this->proyecto_id)) {
            $this->proyecto_id = '';
        }
    }

    #[On('cliente-creado')]
    public function clienteCreado(int $id): void
    {
        unset($this->clientes);
        $this->cliente_id = (string) $id;
        $this->proyecto_id = '';
    }

    #[Computed]
    public function clientes()
    {
        return auth()->user()->clientes()->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
    }

    #[Computed]
    public function proyectosDelCliente()
    {
        return auth()->user()->proyectos()->where('cliente_id', (int) $this->cliente_id)->orderBy('nombre')->get(['id', 'nombre']);
    }

    /**
     * Totales en vivo con la misma fiscalidad que usará el servidor al guardar.
     */
    #[Computed]
    public function importes(): Presupuesto
    {
        $base = collect($this->lineas)->sum(
            fn (array $l): int => (int) round(max(0, (float) $l['cantidad']) * (int) round(max(0, (float) $l['precio']) * 100))
        );
        $tipo = $this->clientes->firstWhere('id', (int) $this->cliente_id)?->tipo ?? TipoCliente::Particular;

        return app(CalculadoraPresupuesto::class)->desdeBase((int) $base, $tipo);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $userId = auth()->id();

        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->where('user_id', $userId)],
            // El proyecto es opcional, pero si viene tiene que ser tuyo Y del mismo cliente.
            'proyecto_id' => ['nullable', 'integer', Rule::exists('proyectos', 'id')->where('user_id', $userId)->where('cliente_id', (int) $this->cliente_id)],
            'concepto' => ['required', 'string', 'min:3', 'max:160'],
            'dias_pago' => ['required', 'integer', Rule::in([0, 15, 30, 45, 60, 90])],
            'notas' => ['nullable', 'string', 'max:1000'],
            // Validación de arrays: "lineas.*.campo" valida cada línea por separado.
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.descripcion' => ['required', 'string', 'max:200'],
            'lineas.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'lineas.*.precio' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'cliente_id.exists' => __('Choose one of your clients.'),
            'proyecto_id.exists' => __('The project must be yours and belong to the selected client.'),
            'lineas.*.descripcion.required' => __('Each line needs a description.'),
            'lineas.*.cantidad.min' => __('The quantity must be greater than 0.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'lineas.*.descripcion' => __('description'),
            'lineas.*.cantidad' => __('quantity'),
            'lineas.*.precio' => __('price'),
        ];
    }

    public function guardar(FacturaService $servicio): void
    {
        $datos = $this->validate();

        if ($this->factura) {
            Gate::authorize('update', $this->factura);
        }

        $factura = $servicio->guardarBorrador(auth()->user(), [
            'cliente_id' => (int) $datos['cliente_id'],
            'proyecto_id' => filled($datos['proyecto_id']) ? (int) $datos['proyecto_id'] : null,
            'concepto' => $datos['concepto'],
            'dias_pago' => (int) $datos['dias_pago'],
            'notas' => filled($datos['notas']) ? $datos['notas'] : null,
        ], array_values($datos['lineas']), $this->factura);

        Flux::toast(variant: 'success', text: __('Draft saved. Review it and click "Issue" when it is ready.'));
        $this->redirectRoute('facturas.show', $factura, navigate: true);
    }
};
?>

<div>
    <x-encabezado :titulo="$factura ? __('Edit draft') : __('New invoice')" :subtitulo="__('Add the lines and the total is calculated instantly.')" />

    <form wire:submit="guardar" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="grid gap-6 sm:grid-cols-2">
                <div class="flex items-end gap-2">
                    <flux:select wire:model.live="cliente_id" :label="__('Client')" class="flex-1">
                        <flux:select.option value="">{{ __('Choose a client…') }}</flux:select.option>
                        @foreach ($this->clientes as $opcion)
                            <flux:select.option :value="$opcion->id">{{ $opcion->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:modal.trigger name="cliente-rapido">
                        <flux:button icon="plus" :aria-label="__('New client')" />
                    </flux:modal.trigger>
                </div>

                <flux:select wire:model="proyecto_id" :label="__('Project')" :badge="__('Optional')" :disabled="! $cliente_id">
                    <flux:select.option value="">{{ __('No project') }}</flux:select.option>
                    @foreach ($this->proyectosDelCliente as $opcion)
                        <flux:select.option :value="$opcion->id">{{ $opcion->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:input wire:model="concepto" :label="__('Concept')" :placeholder="__('E.g.: Corporate website development — phase 1')" required />
                </div>

                <flux:select wire:model="dias_pago" :label="__('Payment terms')" :description:trailing="__('The due date is calculated when the invoice is issued.')">
                    @foreach ([0 => __('Immediate'), 15 => __(':days days', ['days' => 15]), 30 => __(':days days', ['days' => 30]), 45 => __(':days days', ['days' => 45]), 60 => __(':days days', ['days' => 60]), 90 => __(':days days', ['days' => 90])] as $dias => $texto)
                        <flux:select.option :value="$dias">{{ $texto }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="notas" :label="__('Notes for the client')" :badge="__('Optional')" :placeholder="__('E.g.: Thank you for your trust')" />
            </flux:card>

            <flux:card class="p-0">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <flux:heading>{{ __('Lines') }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="anadirLinea">{{ __('Add line') }}</flux:button>
                </div>

                <flux:error name="lineas" class="px-5 pt-3" />

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($lineas as $i => $linea)
                        <div wire:key="linea-{{ $i }}" class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_6rem_8rem_7rem_auto] sm:items-start">
                            <flux:input wire:model="lineas.{{ $i }}.descripcion" :placeholder="__('Description')" :aria-label="__('Description')" />
                            <flux:input wire:model.live.debounce.400ms="lineas.{{ $i }}.cantidad" type="number" step="0.01" min="0.01" :aria-label="__('Quantity')" />
                            <flux:input wire:model.live.debounce.400ms="lineas.{{ $i }}.precio" type="number" step="0.01" min="0" :aria-label="__('Price (€)')" icon:trailing="currency-euro" />
                            <p class="self-center text-end text-sm font-medium tabular-nums">
                                {{ Presupuesto::formatear((int) round(max(0, (float) $linea['cantidad']) * (int) round(max(0, (float) $linea['precio']) * 100))) }}
                            </p>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="quitarLinea({{ $i }})" :disabled="count($lineas) === 1" :aria-label="__('Remove line')" class="self-center" />
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>

        <aside class="h-fit space-y-4 lg:sticky lg:top-6">
            <x-importes :importes="$this->importes" :titulo="__('Totals')" />
            <flux:button type="submit" variant="primary" class="w-full">{{ __('Save draft') }}</flux:button>
            <flux:button variant="ghost" class="w-full" :href="$factura ? route('facturas.show', $factura) : route('facturas.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:text size="sm">{{ __('It is saved as a draft: you can review it and issue it later. Once issued, the invoice can no longer be modified.') }}</flux:text>
        </aside>
    </form>

    <livewire:cliente-rapido />
</div>
