<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk()->assertSee('FacturaFlow');
});

test('the demo seeder creates a complete, working dataset', function () {
    $this->seed();

    $demo = User::query()->where('email', 'demo@facturaflow.test')->sole();

    expect(User::query()->count())->toBe(7)
        ->and($demo->clientes()->count())->toBeGreaterThan(5)
        ->and($demo->facturas()->whereNotNull('numero')->count())->toBeGreaterThan(5)
        ->and($demo->etiquetas()->count())->toBe(6);

    $this->actingAs($demo)->get(route('dashboard'))->assertOk();
});
