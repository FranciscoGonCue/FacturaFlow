<?php

use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Ajustes → Datos fiscales: lo que aparece como EMISOR en tus facturas,
 * más la subida del logo (WithFileUploads de Livewire).
 */
new #[Title('Tax details')] class extends Component
{
    use WithFileUploads;

    public string $nif = '';

    public string $direccion = '';

    public string $ciudad = '';

    public string $iban = '';

    /** Archivo temporal subido por Livewire (se guarda de verdad al pulsar "Guardar"). */
    public $logo = null;

    public function mount(): void
    {
        $usuario = auth()->user();
        $this->nif = (string) $usuario->nif;
        $this->direccion = (string) $usuario->direccion;
        $this->ciudad = (string) $usuario->ciudad;
        $this->iban = $usuario->iban ? trim(chunk_split($usuario->iban, 4, ' ')) : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // NIF/NIE/CIF español: letra o número inicial, 7 dígitos y letra o número final.
            'nif' => ['nullable', 'string', 'regex:/^[A-Z0-9][0-9]{7}[A-Z0-9]$/i'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:80'],
            'iban' => ['nullable', 'string', 'regex:/^ES\d{2}\s?(\d{4}\s?){5}$/i'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nif.regex' => __('The tax ID does not have a valid format (e.g. 12345678Z).'),
            'iban.regex' => __('The IBAN must be Spanish: ES + 22 digits.'),
        ];
    }

    public function guardar(): void
    {
        $this->validate();
        $usuario = auth()->user();

        $datos = [
            'nif' => $this->nif !== '' ? strtoupper($this->nif) : null,
            'direccion' => $this->direccion !== '' ? $this->direccion : null,
            'ciudad' => $this->ciudad !== '' ? $this->ciudad : null,
            'iban' => $this->iban !== '' ? strtoupper(preg_replace('/\s+/', '', $this->iban) ?? '') : null,
        ];

        if ($this->logo) {
            if ($usuario->logo_path) {
                Storage::disk('public')->delete($usuario->logo_path);
            }
            $datos['logo_path'] = $this->logo->store('logos', 'public');
            $this->logo = null;
        }

        $usuario->update($datos);

        Flux::toast(variant: 'success', text: __('Tax details saved.'));
    }

    public function quitarLogo(): void
    {
        $usuario = auth()->user();

        if ($usuario->logo_path) {
            Storage::disk('public')->delete($usuario->logo_path);
            $usuario->update(['logo_path' => null]);
        }
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Tax details')" :subheading="__('This information appears as the issuer on your invoices. Tax ID and address are required to issue.')">
        <form wire:submit="guardar" class="my-6 w-full space-y-6">
            <flux:input wire:model="nif" :label="__('Tax ID')" placeholder="12345678Z" class="uppercase" />
            <flux:input wire:model="direccion" :label="__('Tax address')" :placeholder="__('Street, number, postcode')" />
            <flux:input wire:model="ciudad" :label="__('City')" :badge="__('Optional')" />
            <flux:input wire:model="iban" :label="__('IBAN for payments')" :badge="__('Optional')" placeholder="ES00 0000 0000 0000 0000 0000" class="uppercase" />

            {{-- Subida de archivos con Livewire: vista previa antes de guardar --}}
            <flux:field>
                <flux:label>{{ __('Logo') }}</flux:label>
                <div class="flex items-center gap-4">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-14 w-auto rounded border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-700">
                    @elseif (auth()->user()->logoUrl())
                        <img src="{{ auth()->user()->logoUrl() }}" alt="" class="h-14 w-auto rounded border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-700">
                    @else
                        <span class="grid size-14 place-items-center rounded border border-dashed border-zinc-300 text-zinc-400 dark:border-zinc-600"><flux:icon.photo /></span>
                    @endif
                    <div class="space-y-2">
                        <input type="file" wire:model="logo" accept="image/*" class="block text-sm text-zinc-600 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:text-zinc-300 dark:file:bg-zinc-800">
                        <div wire:loading wire:target="logo" class="text-xs text-indigo-600">{{ __('Uploading…') }}</div>
                        @if (auth()->user()->logo_path && ! $logo)
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="quitarLogo">{{ __('Remove logo') }}</flux:button>
                        @endif
                    </div>
                </div>
                <flux:error name="logo" />
                <flux:description>{{ __('PNG or JPG, maximum 1 MB. It is shown on your invoices.') }}</flux:description>
            </flux:field>

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
