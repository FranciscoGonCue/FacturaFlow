<?php

use App\Enums\EstadoFactura;
use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\FacturacionMensual;
use App\ValueObjects\Presupuesto;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * ZONA ADMIN: resumen global de la plataforma (todos los usuarios).
 * Un freelancer normal nunca llega aquí: la ruta tiene el middleware "admin".
 */
new #[Title('Global overview')] class extends Component
{
    #[Computed]
    public function emitidas()
    {
        return Factura::query()->where('estado', '!=', EstadoFactura::Borrador)->get();
    }

    /**
     * @return array<string, int|string>
     */
    #[Computed]
    public function metricas(): array
    {
        return [
            'usuarios' => User::query()->count(),
            'freelancers' => User::query()->where('rol', Rol::Freelancer)->count(),
            'admins' => User::query()->where('rol', Rol::Administrador)->count(),
            'bloqueados' => User::query()->where('activo', false)->count(),
            'clientes' => Cliente::query()->count(),
            'proyectos' => Proyecto::query()->count(),
            'facturas' => $this->emitidas->count(),
            'facturado' => Presupuesto::formatear((int) $this->emitidas->sum('total_centimos')),
        ];
    }

    #[Computed]
    public function ranking()
    {
        return User::query()
            ->withCount(['clientes', 'facturas'])
            ->withSum(['facturas as facturado' => fn ($q) => $q->where('estado', '!=', EstadoFactura::Borrador)], 'total_centimos')
            ->orderByDesc('facturado')
            ->take(5)
            ->get();
    }
};
?>

<div>
    <x-encabezado :titulo="__('Global overview')" :subtitulo="__('Activity of the whole platform (all users).')">
        <x-slot:acciones>
            <flux:button icon="shield-check" :href="route('admin.usuarios')" wire:navigate>{{ __('Manage users') }}</flux:button>
        </x-slot:acciones>
    </x-encabezado>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metrica :etiqueta="__('Users')" :valor="$this->metricas['usuarios']" icono="users" tono="violet"
            :detalle="__(':f freelancers · :a admins · :b blocked', ['f' => $this->metricas['freelancers'], 'a' => $this->metricas['admins'], 'b' => $this->metricas['bloqueados']])" />
        <x-metrica :etiqueta="__('Clients')" :valor="$this->metricas['clientes']" icono="building-office" />
        <x-metrica :etiqueta="__('Projects')" :valor="$this->metricas['proyectos']" icono="briefcase" tono="amber" />
        <x-metrica :etiqueta="__('Total invoiced')" :valor="$this->metricas['facturado']" icono="banknotes" tono="emerald"
            :detalle="__(':count invoices issued', ['count' => $this->metricas['facturas']])" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-grafico-barras :meses="FacturacionMensual::calcular($this->emitidas)" :titulo="__('Platform invoicing (6 months)')" class="xl:col-span-2" />

        <flux:card>
            <flux:heading>{{ __('Top freelancers') }}</flux:heading>
            <ul class="mt-4 space-y-3">
                @foreach ($this->ranking as $usuario)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <flux:avatar size="xs" :initials="$usuario->initials()" color="auto" :color:seed="$usuario->id" />
                            <span class="truncate">{{ $usuario->name }}</span>
                        </div>
                        <span class="shrink-0 font-medium tabular-nums">{{ Presupuesto::formatear((int) $usuario->facturado) }}</span>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    </div>
</div>
