@props(['icono', 'titulo', 'texto' => null])

{{-- Mensaje de "no hay datos" con icono y, opcionalmente, un botón (slot). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700']) }}>
    <span class="grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
        <flux:icon :name="$icono" class="size-6" />
    </span>
    <flux:heading class="mt-4">{{ $titulo }}</flux:heading>
    @if ($texto)
        <flux:text class="mt-1 max-w-sm">{{ $texto }}</flux:text>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
