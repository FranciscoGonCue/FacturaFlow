@props(['align' => 'start', 'position' => 'top'])

{{-- Componente reutilizable: cambia el idioma de la interfaz (se guarda en la sesión). --}}
<flux:dropdown :position="$position" :align="$align" {{ $attributes }}>
    <flux:button size="sm" variant="ghost" icon="language" icon:trailing="chevron-up-down">
        {{ \App\Http\Middleware\SetLocale::IDIOMAS[app()->getLocale()] ?? app()->getLocale() }}
    </flux:button>

    <flux:menu>
        @foreach (\App\Http\Middleware\SetLocale::IDIOMAS as $codigo => $nombre)
            <form method="POST" action="{{ route('idioma', $codigo) }}">
                @csrf
                <flux:menu.item as="button" type="submit" class="w-full" :icon="app()->getLocale() === $codigo ? 'check' : null">
                    {{ $nombre }}
                </flux:menu.item>
            </form>
        @endforeach
    </flux:menu>
</flux:dropdown>
