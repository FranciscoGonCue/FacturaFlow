<?php

use App\Models\Cliente;
use App\Models\Etiqueta;
use App\Models\Proyecto;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->usuario = User::factory()->create();
    $this->actingAs($this->usuario);
});

test('crea, edita y borra etiquetas', function (): void {
    Livewire::test('pages::etiquetas.index')
        ->call('nueva')
        ->set('nombre', 'Urgente')
        ->set('color', 'red')
        ->call('guardar')
        ->assertHasNoErrors();

    $etiqueta = Etiqueta::query()->sole();
    expect($etiqueta->user_id)->toBe($this->usuario->id);

    Livewire::test('pages::etiquetas.index')
        ->call('editar', $etiqueta->id)
        ->set('nombre', 'Muy urgente')
        ->call('guardar');
    expect($etiqueta->fresh()->nombre)->toBe('Muy urgente');

    Livewire::test('pages::etiquetas.index')
        ->call('confirmarBorrado', $etiqueta->id)
        ->call('eliminar');
    expect(Etiqueta::query()->count())->toBe(0);
});

test('el nombre es único por usuario y el color debe ser válido', function (): void {
    Etiqueta::factory()->for($this->usuario)->create(['nombre' => 'Web']);
    Etiqueta::factory()->create(['nombre' => 'Diseño']);

    Livewire::test('pages::etiquetas.index')
        ->set('nombre', 'Web')->set('color', 'morado-fosforito')
        ->call('guardar')
        ->assertHasErrors(['nombre' => 'unique', 'color']);

    Livewire::test('pages::etiquetas.index')
        ->set('nombre', 'Diseño')->set('color', 'pink')
        ->call('guardar')
        ->assertHasNoErrors();
});

test('al borrar una etiqueta los proyectos se conservan', function (): void {
    $etiqueta = Etiqueta::factory()->for($this->usuario)->create();
    $proyecto = Proyecto::factory()->for(Cliente::factory()->for($this->usuario))->create();
    $proyecto->etiquetas()->attach($etiqueta);

    Livewire::test('pages::etiquetas.index')->call('confirmarBorrado', $etiqueta->id)->call('eliminar');

    expect($proyecto->fresh())->not->toBeNull()
        ->and($proyecto->fresh()->etiquetas)->toHaveCount(0);
});

test('no puede editar etiquetas ajenas', function (): void {
    $ajena = Etiqueta::factory()->create();

    Livewire::test('pages::etiquetas.index')->call('editar', $ajena->id)->assertForbidden();
});
