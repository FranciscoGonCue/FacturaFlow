<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guarda los datos fiscales normalizados', function (): void {
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test('pages::settings.fiscal')
        ->set('nif', '87654321x')
        ->set('direccion', 'Gran Vía 1')
        ->set('iban', 'es91 2100 0418 4502 0005 1332')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($usuario->fresh()->nif)->toBe('87654321X')
        ->and($usuario->fresh()->iban)->toBe('ES9121000418450200051332')
        ->and($usuario->fresh()->tieneDatosFiscales())->toBeTrue();
});

test('rechaza un NIF o un IBAN con formato incorrecto', function (): void {
    Livewire::actingAs(User::factory()->create())->test('pages::settings.fiscal')
        ->set('nif', '123')
        ->set('iban', 'FR76 1234')
        ->call('guardar')
        ->assertHasErrors(['nif', 'iban']);
});

test('sube el logo del freelancer', function (): void {
    Storage::fake('public');
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test('pages::settings.fiscal')
        ->set('logo', UploadedFile::fake()->image('logo.png', 200, 80))
        ->call('guardar')
        ->assertHasNoErrors();

    expect($usuario->fresh()->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($usuario->fresh()->logo_path);
});
