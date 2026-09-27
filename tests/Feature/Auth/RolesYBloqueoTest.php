<?php

use App\Enums\Rol;
use App\Models\User;

test('los usuarios registrados son freelancers aunque intenten enviar rol=admin', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Pirata',
        'email' => 'pirata@test.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'rol' => 'admin',
    ]);

    expect(User::query()->where('email', 'pirata@test.test')->sole()->rol)->toBe(Rol::Freelancer);
});

test('una cuenta bloqueada no puede iniciar sesión', function (): void {
    $usuario = User::factory()->bloqueado()->create();

    $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('si se bloquea una cuenta con la sesión abierta, se cierra su sesión', function (): void {
    $usuario = User::factory()->create();
    $this->actingAs($usuario)->get(route('dashboard'))->assertOk();

    $usuario->forceFill(['activo' => false])->save();

    $this->actingAs($usuario)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('un freelancer no puede entrar en la zona de administración', function (string $ruta): void {
    $this->actingAs(User::factory()->create())->get(route($ruta))->assertForbidden();
})->with(['admin.panel', 'admin.usuarios']);

test('un administrador sí puede entrar en la zona de administración', function (string $ruta): void {
    $this->actingAs(User::factory()->administrador()->create())->get(route($ruta))->assertOk();
})->with(['admin.panel', 'admin.usuarios']);

test('un invitado es enviado al login desde las zonas privadas', function (string $ruta): void {
    $this->get(route($ruta))->assertRedirect(route('login'));
})->with(['dashboard', 'clientes.index', 'proyectos.index', 'proyectos.tablero', 'facturas.index', 'etiquetas.index', 'admin.panel']);

test('se puede cambiar el idioma de la interfaz', function (): void {
    $this->post(route('idioma', 'en'))->assertRedirect();
    $this->get(route('login'))->assertSee('Log in');

    $this->post(route('idioma', 'es'));
    $this->get(route('login'))->assertSee('Iniciar sesión');
});
