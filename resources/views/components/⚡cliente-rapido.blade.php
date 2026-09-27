<?php

use App\Enums\TipoCliente;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

/*
 * Componente reutilizable (no es una página): crear un cliente sin salir del formulario
 * de proyecto o de factura. Al guardar, avisa al componente padre con el evento
 * "cliente-creado" y el padre lo selecciona automáticamente.
 */
new class extends Component
{
    public string $nombre = '';

    public string $email = '';

    public string $tipo = 'empresa';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('clientes', 'email')->where('user_id', auth()->id())],
            'tipo' => ['required', Rule::enum(TipoCliente::class)],
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
        $cliente = auth()->user()->clientes()->create($this->validate());

        $this->reset();
        Flux::modal('cliente-rapido')->close();
        Flux::toast(variant: 'success', text: __('Client ":name" created.', ['name' => $cliente->nombre]));

        // Evento Livewire: lo escucha el formulario padre con #[On('cliente-creado')].
        $this->dispatch('cliente-creado', id: $cliente->id);
    }
};
?>

<flux:modal name="cliente-rapido" class="md:w-md">
    <form wire:submit="guardar" class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Quick client') }}</flux:heading>
            <flux:text class="mt-1">{{ __('You can complete the rest of the details later.') }}</flux:text>
        </div>

        <flux:input wire:model="nombre" :label="__('Name or company name')" />
        <flux:input wire:model="email" type="email" :label="__('Email')" />
        <flux:select wire:model="tipo" :label="__('Type')">
            @foreach (TipoCliente::cases() as $opcion)
                <flux:select.option :value="$opcion->value">{{ $opcion->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Create client') }}</flux:button>
        </div>
    </form>
</flux:modal>
