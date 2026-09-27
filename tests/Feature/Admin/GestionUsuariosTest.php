<?php

use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\User;
use App\Services\FacturaService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->administrador()->create();
    $this->actingAs($this->admin);
});

test('el admin ve y filtra a todos los usuarios', function (): void {
    User::factory()->create(['name' => 'Freelancer Activo']);
    User::factory()->bloqueado()->create(['name' => 'Freelancer Bloqueado']);

    Livewire::test('pages::admin.usuarios')
        ->assertSee('Freelancer Activo')
        ->assertSee('Freelancer Bloqueado')
        ->set('estado', 'bloqueado')
        ->assertSee('Freelancer Bloqueado')
        ->assertDontSee('Freelancer Activo')
        ->set('estado', '')
        ->set('rol', 'admin')
        ->assertSee($this->admin->name)
        ->assertDontSee('Freelancer Activo');
});

test('el admin cambia el rol y bloquea cuentas', function (): void {
    $usuario = User::factory()->create();

    Livewire::test('pages::admin.usuarios')
        ->call('cambiarRol', $usuario->id, 'admin')
        ->call('alternarBloqueo', $usuario->id);

    expect($usuario->fresh()->rol)->toBe(Rol::Administrador)
        ->and($usuario->fresh()->activo)->toBeFalse();
});

test('el admin no puede degradarse ni bloquearse a sí mismo', function (): void {
    Livewire::test('pages::admin.usuarios')
        ->call('cambiarRol', $this->admin->id, 'freelancer')
        ->assertForbidden();

    Livewire::test('pages::admin.usuarios')
        ->call('alternarBloqueo', $this->admin->id)
        ->assertForbidden();

    expect($this->admin->fresh()->esAdministrador())->toBeTrue();
});

test('al borrar un usuario se borran todos sus datos', function (): void {
    $usuario = User::factory()->conDatosFiscales()->create();
    $cliente = Cliente::factory()->for($usuario)->create();
    $factura = app(FacturaService::class)->guardarBorrador($usuario, ['cliente_id' => $cliente->id, 'concepto' => 'X', 'dias_pago' => 30], [['descripcion' => 'a', 'cantidad' => 1, 'precio' => 10]]);
    app(FacturaService::class)->emitir($factura);

    Livewire::test('pages::admin.usuarios')
        ->call('confirmarBorrado', $usuario->id)
        ->call('eliminar');

    expect(User::query()->find($usuario->id))->toBeNull()
        ->and(Cliente::query()->count())->toBe(0)
        ->and(Factura::query()->count())->toBe(0);
});

test('un freelancer no puede usar las acciones del admin aunque llame al componente', function (): void {
    $freelancer = User::factory()->create();
    $otro = User::factory()->create();

    Livewire::actingAs($freelancer)
        ->test('pages::admin.usuarios')
        ->call('cambiarRol', $otro->id, 'admin')
        ->assertForbidden();
});

test('el panel global muestra las métricas de la plataforma', function (): void {
    User::factory(3)->create();

    Livewire::test('pages::admin.panel')->assertOk()->assertSee('4');
});
