<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head', ['title' => __('Management for freelancers')])
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-5 sm:px-6">
        <x-app-logo href="{{ route('home') }}" />
        <div class="flex items-center gap-2">
            <x-selector-idioma position="bottom" align="end" />
            @auth
                <flux:button variant="primary" :href="route('dashboard')">{{ __('Go to dashboard') }}</flux:button>
            @else
                <flux:button variant="ghost" :href="route('login')" class="max-sm:hidden">{{ __('Log in') }}</flux:button>
                <flux:button variant="primary" :href="route('register')">{{ __('Create account') }}</flux:button>
            @endauth
        </div>
    </header>

    <main>
        <section class="relative isolate overflow-hidden">
            <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu blur-3xl" aria-hidden="true">
                <div class="mx-auto aspect-[1155/678] w-[72rem] bg-gradient-to-tr from-indigo-400 to-violet-400 opacity-20"
                    style="clip-path: polygon(74% 44%, 100% 61%, 97% 26%, 85% 0%, 80% 2%, 72% 32%, 60% 62%, 52% 68%, 47% 58%, 45% 34%, 27% 76%, 0% 64%, 17% 100%, 27% 76%, 76% 97%, 74% 44%)"></div>
            </div>

            <div class="mx-auto max-w-6xl px-4 pt-16 pb-12 text-center sm:px-6 sm:pt-24">
                <flux:badge color="indigo" icon="bolt">{{ __('Built with Laravel 13 and the TALL stack') }}</flux:badge>
                <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                    {{ __('Your freelance business,') }}
                    <span class="bg-gradient-to-r from-indigo-600 to-violet-500 bg-clip-text text-transparent">{{ __('under control') }}</span>
                </h1>
                <p class="mx-auto mt-6 max-w-xl text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    {{ __('Organise clients and projects, calculate quotes with VAT and income tax instantly, issue numbered invoices and spot late payments before they become a problem.') }}
                </p>
                <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
                    <flux:button variant="primary" :href="route('register')">{{ __('Start for free') }}</flux:button>
                    <flux:button :href="route('login')">{{ __('See the demo') }}</flux:button>
                </div>
                <flux:text size="sm" class="mt-4">{{ __('Demo') }}: demo@facturaflow.test · {{ __('password') }}: password</flux:text>
            </div>
        </section>

        <section class="mx-auto grid max-w-6xl gap-4 px-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
            @foreach ([
                ['icono' => 'users', 'titulo' => __('Clients'), 'texto' => __('Companies, self-employed and individuals, with their tax treatment applied automatically.')],
                ['icono' => 'view-columns', 'titulo' => __('Projects'), 'texto' => __('Statuses, tags, rates and deadlines. Live filters and a drag-and-drop board.')],
                ['icono' => 'document-text', 'titulo' => __('Invoices'), 'texto' => __('Correlative numbering, lines, VAT and withholding, email delivery and CSV export.')],
                ['icono' => 'chart-bar', 'titulo' => __('Dashboard'), 'texto' => __('Invoiced this year, pending payments, overdue invoices and late deliveries at a glance.')],
            ] as $caracteristica)
                <flux:card>
                    <span class="grid size-10 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <flux:icon :name="$caracteristica['icono']" class="size-5" />
                    </span>
                    <flux:heading class="mt-4">{{ $caracteristica['titulo'] }}</flux:heading>
                    <flux:text class="mt-2">{{ $caracteristica['texto'] }}</flux:text>
                </flux:card>
            @endforeach
        </section>
    </main>

    <footer class="mt-24 border-t border-zinc-200 py-8 text-center text-sm text-zinc-500 dark:border-zinc-800">
        FacturaFlow · {{ __('Web Development academic project') }} · Laravel 13 + TALL
    </footer>

    @fluxScripts
</body>
</html>
