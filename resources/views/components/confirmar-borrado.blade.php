@props(['nombre', 'titulo', 'mensaje', 'accion', 'boton' => null])

{{--
    Modal de confirmación reutilizable (Flux modal, que funciona con Alpine por dentro).
    Se abre con <flux:modal.trigger name="..."> y el botón rojo llama al método Livewire "accion".
--}}
<flux:modal :name="$nombre" class="md:w-md">
    <div class="space-y-6">
        <div class="flex gap-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                <flux:icon.exclamation-triangle class="size-5" />
            </span>
            <div>
                <flux:heading size="lg">{{ $titulo }}</flux:heading>
                <flux:text class="mt-2">{{ $mensaje }}</flux:text>
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="{{ $accion }}">{{ $boton ?? __('Delete') }}</flux:button>
        </div>
    </div>
</flux:modal>
