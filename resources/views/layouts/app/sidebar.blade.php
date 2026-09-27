<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Management')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('clientes.index')" :current="request()->routeIs('clientes.*')" wire:navigate>
                        {{ __('Clients') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="briefcase" :href="route('proyectos.index')" :current="request()->routeIs('proyectos.*') && ! request()->routeIs('proyectos.tablero')" wire:navigate>
                        {{ __('Projects') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="view-columns" :href="route('proyectos.tablero')" :current="request()->routeIs('proyectos.tablero')" wire:navigate>
                        {{ __('Board') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-text" :href="route('facturas.index')" :current="request()->routeIs('facturas.*')" wire:navigate>
                        {{ __('Invoices') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('etiquetas.index')" :current="request()->routeIs('etiquetas.*')" wire:navigate>
                        {{ __('Tags') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Solo los administradores ven esta sección (además, las rutas tienen el middleware "admin"). --}}
                @if (auth()->user()->esAdministrador())
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.panel')" :current="request()->routeIs('admin.panel')" wire:navigate>
                            {{ __('Global overview') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" :href="route('admin.usuarios')" :current="request()->routeIs('admin.usuarios')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-selector-idioma class="px-1" />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('fiscal.edit')" icon="identification" wire:navigate>
                            {{ __('Tax details') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
