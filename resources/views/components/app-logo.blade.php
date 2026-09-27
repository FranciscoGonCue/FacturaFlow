@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="FacturaFlow" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="FacturaFlow" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:brand>
@endif
