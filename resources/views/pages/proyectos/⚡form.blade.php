<?php

use App\Enums\EstadoProyecto;
use App\Enums\TipoCliente;
use App\Models\Proyecto;
use App\Services\CalculadoraPresupuesto;
use App\ValueObjects\Presupuesto;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Formulario de proyecto (crear y editar). Incluye:
 *  - presupuesto calculado EN VIVO en el servidor (CalculadoraPresupuesto) mientras escribes;
 *  - selección de etiquetas (relación muchos a muchos, se guarda con sync());
 *  - el componente <livewire:cliente-rapido /> para crear un cliente sin salir.
 */
new #[Title('Project')] class extends Component
{
    public ?Proyecto $proyecto = null;

    public string $cliente_id = '';

    public string $nombre = '';

    public string $descripcion = '';

    public string $estado = 'propuesta';

    public string $tarifa_hora = '45';

    public string $horas_estimadas = '40';

    public string $fecha_inicio = '';

    public string $fecha_entrega = '';

    /** @var list<string> ids de las etiquetas marcadas */
    public array $etiquetas = [];

    public function mount(?Proyecto $proyecto = null): void
    {
        if ($proyecto?->exists) {
            Gate::authorize('update', $proyecto);
            $this->proyecto = $proyecto;
            $this->fill([
                'cliente_id' => (string) $proyecto->cliente_id,
                'nombre' => $proyecto->nombre,
                'descripcion' => (string) $proyecto->descripcion,
                'estado' => $proyecto->estado->value,
                'tarifa_hora' => (string) (float) $proyecto->tarifa_hora,
                'horas_estimadas' => (string) $proyecto->horas_estimadas,
                'fecha_inicio' => (string) $proyecto->fecha_inicio?->format('Y-m-d'),
                'fecha_entrega' => (string) $proyecto->fecha_entrega?->format('Y-m-d'),
                'etiquetas' => $proyecto->etiquetas->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ]);
        } else {
            // Si llegas desde la ficha de un cliente (?cliente=5), queda preseleccionado.
            $this->cliente_id = (string) request()->integer('cliente') ?: '';
        }
    }

    /**
     * El cliente rápido avisa con este evento cuando crea un cliente: lo seleccionamos.
     */
    #[On('cliente-creado')]
    public function clienteCreado(int $id): void
    {
        unset($this->clientes);
        $this->cliente_id = (string) $id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $userId = auth()->id();

        return [
            // Seguridad: el cliente debe existir Y ser tuyo.
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->where('user_id', $userId)],
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'estado' => ['required', Rule::enum(EstadoProyecto::class)],
            'tarifa_hora' => ['required', 'numeric', 'min:0', 'max:1000'],
            'horas_estimadas' => ['required', 'integer', 'min:1', 'max:5000'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_entrega' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'etiquetas' => ['array'],
            'etiquetas.*' => ['integer', Rule::exists('etiquetas', 'id')->where('user_id', $userId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'cliente_id.exists' => __('Choose one of your clients.'),
            'fecha_entrega.after_or_equal' => __('The delivery date cannot be before the start date.'),
        ];
    }

    #[Computed]
    public function clientes()
    {
        return auth()->user()->clientes()->orderBy('nombre')->get(['id', 'nombre', 'tipo']);
    }

    #[Computed]
    public function etiquetasDisponibles()
    {
        return auth()->user()->etiquetas()->orderBy('nombre')->get();
    }

    /**
     * Presupuesto en vivo: se recalcula en el servidor cada vez que cambian la tarifa, las horas o el cliente.
     */
    #[Computed]
    public function presupuesto(): Presupuesto
    {
        $tipo = $this->clientes->firstWhere('id', (int) $this->cliente_id)?->tipo ?? TipoCliente::Particular;

        return app(CalculadoraPresupuesto::class)->calcular(
            max(0, (float) $this->tarifa_hora),
            max(0, (int) $this->horas_estimadas),
            $tipo,
        );
    }

    public function guardar(): void
    {
        $datos = $this->validate();
        $etiquetas = array_map('intval', $datos['etiquetas'] ?? []);
        unset($datos['etiquetas']);
        $datos = array_map(fn ($valor) => $valor === '' ? null : $valor, $datos);

        if ($this->proyecto) {
            Gate::authorize('update', $this->proyecto);
            $this->proyecto->update($datos);
            $proyecto = $this->proyecto;
        } else {
            $proyecto = auth()->user()->proyectos()->create($datos);
        }

        // sync() deja en la tabla pivote EXACTAMENTE estas etiquetas (añade las nuevas y quita las demás).
        $proyecto->etiquetas()->sync($etiquetas);

        Flux::toast(variant: 'success', text: __('Project saved.'));
        $this->redirectRoute('proyectos.show', $proyecto, navigate: true);
    }
};
?>

<div>
    <x-encabezado :titulo="$proyecto ? __('Edit project') : __('New project')" :subtitulo="__('The quote is calculated as you type.')" />

    <form wire:submit="guardar" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-6">
                <div class="flex items-end gap-2">
                    <flux:select wire:model.live="cliente_id" :label="__('Client')" class="flex-1">
                        <flux:select.option value="">{{ __('Choose a client…') }}</flux:select.option>
                        @foreach ($this->clientes as $opcion)
                            <flux:select.option :value="$opcion->id">{{ $opcion->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:modal.trigger name="cliente-rapido">
                        <flux:button icon="plus" :aria-label="__('New client')">{{ __('New') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:input wire:model="nombre" :label="__('Project name')" required />
                <flux:textarea wire:model="descripcion" :label="__('Description')" :badge="__('Optional')" rows="3" />

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model.live.debounce.300ms="tarifa_hora" type="number" step="0.01" min="0" :label="__('Hourly rate (€)')" required />
                    <flux:input wire:model.live.debounce.300ms="horas_estimadas" type="number" min="1" :label="__('Estimated hours')" required />
                    <flux:input wire:model="fecha_inicio" type="date" :label="__('Start')" :badge="__('Optional')" />
                    <flux:input wire:model="fecha_entrega" type="date" :label="__('Delivery')" :badge="__('Optional')" />
                </div>

                <flux:radio.group wire:model="estado" :label="__('Status')" variant="segmented">
                    @foreach (EstadoProyecto::cases() as $opcion)
                        <flux:radio :value="$opcion->value" :label="$opcion->label()" />
                    @endforeach
                </flux:radio.group>
            </flux:card>

            {{-- Etiquetas: casillas que se guardan en la tabla pivote (muchos a muchos) --}}
            <flux:card>
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading>{{ __('Tags') }}</flux:heading>
                    <flux:link :href="route('etiquetas.index')" wire:navigate class="text-sm">{{ __('Manage tags') }}</flux:link>
                </div>
                @if ($this->etiquetasDisponibles->isEmpty())
                    <flux:text>{{ __('You have no tags yet.') }}</flux:text>
                @else
                    <flux:checkbox.group wire:model="etiquetas" variant="pills" class="flex flex-wrap gap-2">
                        @foreach ($this->etiquetasDisponibles as $etiqueta)
                            <flux:checkbox :value="(string) $etiqueta->id" :label="$etiqueta->nombre" />
                        @endforeach
                    </flux:checkbox.group>
                @endif
            </flux:card>
        </div>

        <aside class="h-fit space-y-4 lg:sticky lg:top-6">
            <x-importes :importes="$this->presupuesto" :titulo="__('Quote')" wire:loading.class="opacity-60" wire:target="tarifa_hora,horas_estimadas,cliente_id" />
            <flux:button type="submit" variant="primary" class="w-full">{{ __('Save project') }}</flux:button>
            <flux:button variant="ghost" class="w-full" :href="$proyecto ? route('proyectos.show', $proyecto) : route('proyectos.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </aside>
    </form>

    <livewire:cliente-rapido />
</div>
