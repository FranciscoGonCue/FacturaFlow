<?php

use App\Models\Etiqueta;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * CRUD completo de etiquetas en una sola página: crear, editar en un modal y borrar.
 * Las etiquetas se unen a los proyectos con una relación MUCHOS A MUCHOS.
 */
new #[Title('Tags')] class extends Component
{
    public string $nombre = '';

    public string $color = 'indigo';

    /** Etiqueta que se está editando en el modal (null = creando una nueva). */
    public ?int $editando = null;

    public ?int $aEliminar = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'min:2', 'max:40',
                Rule::unique('etiquetas', 'nombre')->where('user_id', auth()->id())->ignore($this->editando),
            ],
            'color' => ['required', Rule::in(Etiqueta::COLORES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['nombre.unique' => __('You already have a tag with this name.')];
    }

    public function nueva(): void
    {
        $this->reset('nombre', 'color', 'editando');
        $this->resetValidation();
        Flux::modal('etiqueta')->show();
    }

    public function editar(int $id): void
    {
        $etiqueta = Etiqueta::query()->findOrFail($id);
        Gate::authorize('update', $etiqueta);

        $this->editando = $etiqueta->id;
        $this->nombre = $etiqueta->nombre;
        $this->color = $etiqueta->color;
        $this->resetValidation();
        Flux::modal('etiqueta')->show();
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        if ($this->editando) {
            $etiqueta = Etiqueta::query()->findOrFail($this->editando);
            Gate::authorize('update', $etiqueta);
            $etiqueta->update($datos);
        } else {
            auth()->user()->etiquetas()->create($datos);
        }

        Flux::modal('etiqueta')->close();
        Flux::toast(variant: 'success', text: __('Tag saved.'));
        $this->reset('nombre', 'color', 'editando');
    }

    public function confirmarBorrado(int $id): void
    {
        $this->aEliminar = $id;
        Flux::modal('borrar-etiqueta')->show();
    }

    public function eliminar(): void
    {
        $etiqueta = Etiqueta::query()->findOrFail($this->aEliminar);
        Gate::authorize('delete', $etiqueta);

        // Al borrar la etiqueta, la BD borra sola sus filas de la pivote (cascadeOnDelete). Los proyectos se quedan.
        $etiqueta->delete();

        $this->aEliminar = null;
        Flux::modal('borrar-etiqueta')->close();
        Flux::toast(variant: 'success', text: __('Tag deleted.'));
    }

    #[Computed]
    public function etiquetas()
    {
        return auth()->user()->etiquetas()->withCount('proyectos')->orderBy('nombre')->get();
    }
};
?>

<div>
    <x-encabezado :titulo="__('Tags')" :subtitulo="__('Classify your projects. A project can have several tags and a tag can be in many projects.')">
        <x-slot:acciones>
            <flux:button variant="primary" icon="plus" wire:click="nueva">{{ __('New tag') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    @if ($this->etiquetas->isEmpty())
        <x-estado-vacio icono="tag" :titulo="__('You have no tags yet')" :texto="__('Create tags like «Urgent», «Web» or «Maintenance» and filter your projects by them.')">
            <flux:button variant="primary" wire:click="nueva">{{ __('Create tag') }}</flux:button>
        </x-estado-vacio>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->etiquetas as $etiqueta)
                <flux:card wire:key="etiqueta-{{ $etiqueta->id }}" class="flex items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <flux:badge :color="$etiqueta->color" icon="tag">{{ $etiqueta->nombre }}</flux:badge>
                        <flux:text size="sm" class="mt-2">
                            <a href="{{ route('proyectos.index', ['etiqueta' => $etiqueta->id]) }}" wire:navigate class="hover:underline">
                                {{ trans_choice(':count project|:count projects', $etiqueta->proyectos_count, ['count' => $etiqueta->proyectos_count]) }}
                            </a>
                        </flux:text>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editar({{ $etiqueta->id }})" :aria-label="__('Edit')" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmarBorrado({{ $etiqueta->id }})" :aria-label="__('Delete')" />
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Modal para crear / editar --}}
    <flux:modal name="etiqueta" class="md:w-md">
        <form wire:submit="guardar" class="space-y-5">
            <flux:heading size="lg">{{ $editando ? __('Edit tag') : __('New tag') }}</flux:heading>
            <flux:input wire:model="nombre" :label="__('Name')" maxlength="40" />

            <flux:radio.group wire:model.live="color" :label="__('Color')" variant="pills" class="flex flex-wrap gap-2">
                @foreach (Etiqueta::COLORES as $opcion)
                    <flux:radio :value="$opcion" :label="$opcion" />
                @endforeach
            </flux:radio.group>

            <div class="flex items-center justify-between gap-2">
                <flux:badge :color="$color" icon="tag">{{ $nombre ?: __('Preview') }}</flux:badge>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <x-confirmar-borrado nombre="borrar-etiqueta" accion="eliminar" :titulo="__('Delete this tag?')"
        :mensaje="__('It will be removed from all projects. The projects themselves are not deleted.')" />
</div>
