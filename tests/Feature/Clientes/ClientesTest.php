<?php

use App\Enums\EstadoProyecto;
use App\Enums\TipoCliente;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->usuario = User::factory()->create();
    $this->actingAs($this->usuario);
});

test('la lista muestra solo mis clientes y busca y filtra en vivo', function (): void {
    Cliente::factory()->for($this->usuario)->tipo(TipoCliente::Empresa)->create(['nombre' => 'Panadería Sol']);
    Cliente::factory()->for($this->usuario)->tipo(TipoCliente::Particular)->create(['nombre' => 'Taller Luna']);
    Cliente::factory()->create(['nombre' => 'Cliente Ajeno']);

    Livewire::test('pages::clientes.index')
        ->assertSee('Panadería Sol')
        ->assertDontSee('Cliente Ajeno')
        ->set('buscar', 'pana')
        ->assertSee('Panadería Sol')
        ->assertDontSee('Taller Luna')
        ->set('buscar', '')
        ->set('tipo', 'particular')
        ->assertSee('Taller Luna')
        ->assertDontSee('Panadería Sol');
});

test('crea un cliente con el formulario', function (): void {
    Livewire::test('pages::clientes.form')
        ->set('tipo', 'empresa')
        ->set('nombre', 'Acme S.L.')
        ->set('email', 'hola@acme.test')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect();

    $cliente = Cliente::query()->sole();
    expect($cliente->user_id)->toBe($this->usuario->id)
        ->and($cliente->tipo)->toBe(TipoCliente::Empresa)
        ->and($cliente->telefono)->toBeNull();
});

test('valida los campos y el email único dentro de la cuenta', function (): void {
    Cliente::factory()->for($this->usuario)->create(['email' => 'repe@test.test']);
    Cliente::factory()->create(['email' => 'otra@test.test']);

    Livewire::test('pages::clientes.form')->call('guardar')->assertHasErrors(['nombre', 'email']);

    Livewire::test('pages::clientes.form')
        ->set('nombre', 'Otro')->set('email', 'repe@test.test')
        ->call('guardar')->assertHasErrors(['email' => 'unique']);

    Livewire::test('pages::clientes.form')
        ->set('nombre', 'Otro')->set('email', 'otra@test.test')
        ->call('guardar')->assertHasNoErrors();
});

test('edita un cliente propio', function (): void {
    $cliente = Cliente::factory()->for($this->usuario)->create();

    Livewire::test('pages::clientes.form', ['cliente' => $cliente])
        ->assertSet('email', $cliente->email)
        ->set('nombre', 'Nombre nuevo')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($cliente->fresh()->nombre)->toBe('Nombre nuevo');
});

test('no puede ver ni editar clientes de otro usuario', function (): void {
    $ajeno = Cliente::factory()->create();

    $this->get(route('clientes.show', $ajeno))->assertForbidden();
    $this->get(route('clientes.edit', $ajeno))->assertForbidden();
});

test('no se puede borrar un cliente con proyectos activos, pero sí uno sin ellos', function (): void {
    $conActivo = Cliente::factory()->for($this->usuario)->create();
    Proyecto::factory()->for($conActivo)->estado(EstadoProyecto::EnCurso)->create();
    $libre = Cliente::factory()->for($this->usuario)->create();

    Livewire::test('pages::clientes.index')
        ->call('confirmarBorrado', $conActivo->id)
        ->call('eliminar');
    expect($conActivo->fresh())->not->toBeNull();

    Livewire::test('pages::clientes.index')
        ->call('confirmarBorrado', $libre->id)
        ->call('eliminar');
    expect($libre->fresh())->toBeNull();
});

test('no puede borrar un cliente ajeno desde el listado', function (): void {
    $ajeno = Cliente::factory()->create();

    Livewire::test('pages::clientes.index')
        ->call('confirmarBorrado', $ajeno->id)
        ->call('eliminar')
        ->assertForbidden();
});

test('la ficha muestra los proyectos del cliente', function (): void {
    $cliente = Cliente::factory()->for($this->usuario)->create();
    Proyecto::factory()->for($cliente)->create(['nombre' => 'Tienda online']);

    $this->get(route('clientes.show', $cliente))->assertOk()->assertSee('Tienda online');
});
