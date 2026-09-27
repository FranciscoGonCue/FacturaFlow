<?php

use App\Enums\TipoCliente;
use App\Models\Cliente;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Formulario de cliente. El MISMO componente sirve para crear (/clientes/crear)
 * y para editar (/clientes/{cliente}/editar): si llega un cliente, se edita.
 */
new #[Title('Client')] class extends Component
{
    public ?Cliente $cliente = null;

    public string $tipo = 'empresa';

    public string $nombre = '';

    public string $email = '';

    public string $telefono = '';

    public string $nif = '';

    public string $ciudad = '';

    public string $notas = '';

    public function mount(?Cliente $cliente = null): void
    {
        if ($cliente?->exists) {
            Gate::authorize('update', $cliente);
            $this->cliente = $cliente;
            $this->fill([
                'tipo' => $cliente->tipo->value,
                ...$cliente->only(['nombre', 'email']),
                'telefono' => (string) $cliente->telefono,
                'nif' => (string) $cliente->nif,
                'ciudad' => (string) $cliente->ciudad,
                'notas' => (string) $cliente->notas,
            ]);
        }
    }

    /**
     * Validación en el servidor. El email es único SOLO dentro de tu cuenta.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoCliente::class)],
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required', 'email', 'max:160',
                Rule::unique('clientes', 'email')->where('user_id', auth()->id())->ignore($this->cliente?->id),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'nif' => ['nullable', 'string', 'max:20'],
            'ciudad' => ['nullable', 'string', 'max:80'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['email.unique' => __('You already have a client with this email.')];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        // Los campos opcionales vacíos se guardan como NULL.
        $datos = array_map(fn ($valor) => $valor === '' ? null : $valor, $datos);

        if ($this->cliente) {
            Gate::authorize('update', $this->cliente);
            $this->cliente->update($datos);
            $cliente = $this->cliente;
        } else {
            // Creando a través de la relación, Laravel rellena user_id automáticamente.
            $cliente = auth()->user()->clientes()->create($datos);
        }

        Flux::toast(variant: 'success', text: __('Client saved.'));
        $this->redirectRoute('clientes.show', $cliente, navigate: true);
    }
};
?>

<div>
    <x-encabezado :titulo="$cliente ? __('Edit client') : __('New client')" :subtitulo="$cliente?->nombre ?? __('Add their contact and tax details.')" />

    <form wire:submit="guardar" class="max-w-3xl space-y-6">
        <flux:card class="space-y-6">
            <flux:radio.group wire:model.live="tipo" :label="__('Client type')" variant="segmented">
                @foreach (TipoCliente::cases() as $opcion)
                    <flux:radio :value="$opcion->value" :label="$opcion->label()" />
                @endforeach
            </flux:radio.group>
            <flux:text size="sm" class="-mt-4">
                {{ TipoCliente::from($tipo)->aplicaRetencionIrpf() ? __('Companies and self-employed clients get a 15 % income tax withholding on quotes.') : __('Individuals have no income tax withholding.') }}
            </flux:text>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="nombre" :label="__('Name or company name')" required />
                <flux:input wire:model="email" type="email" :label="__('Email')" required />
                <flux:input wire:model="telefono" type="tel" :label="__('Phone')" :badge="__('Optional')" />
                <flux:input wire:model="nif" :label="__('Tax ID')" :badge="__('Optional')" class="uppercase" />
                <flux:input wire:model="ciudad" :label="__('City')" :badge="__('Optional')" />
            </div>

            {{-- Alpine: contador de caracteres en vivo, sin preguntar al servidor --}}
            <div x-data="{ texto: $wire.entangle('notas') }">
                <flux:textarea wire:model="notas" :label="__('Internal notes')" :badge="__('Optional')" rows="3" maxlength="1000" />
                <flux:text size="sm" class="mt-1 text-end"><span x-text="(texto || '').length">0</span>/1000</flux:text>
            </div>
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="$cliente ? route('clientes.show', $cliente) : route('clientes.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit">{{ __('Save client') }}</flux:button>
        </div>
    </form>
</div>
