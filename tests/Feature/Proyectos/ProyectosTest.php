<?php

use App\Enums\EstadoProyecto;
use App\Enums\TipoCliente;
use App\Models\Cliente;
use App\Models\Etiqueta;
use App\Models\Proyecto;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->usuario = User::factory()->create();
    $this->cliente = Cliente::factory()->for($this->usuario)->tipo(TipoCliente::Empresa)->create(['nombre' => 'Estudio Norte']);
    $this->actingAs($this->usuario);
});

test('la lista combina búsqueda, estado, cliente y etiqueta a la vez', function (): void {
    $web = Etiqueta::factory()->for($this->usuario)->create(['nombre' => 'Web']);
    $otroCliente = Cliente::factory()->for($this->usuario)->create();

    $a = Proyecto::factory()->for($this->cliente)->estado(EstadoProyecto::EnCurso)->create(['nombre' => 'Tienda online']);
    $a->etiquetas()->attach($web);
    Proyecto::factory()->for($this->cliente)->estado(EstadoProyecto::Pausado)->create(['nombre' => 'Tienda física']);
    Proyecto::factory()->for($otroCliente)->estado(EstadoProyecto::EnCurso)->create(['nombre' => 'Tienda de otro cliente']);
    Proyecto::factory()->create(['nombre' => 'Proyecto ajeno']);

    Livewire::test('pages::proyectos.index')
        ->assertDontSee('Proyecto ajeno')
        ->set('buscar', 'tienda')
        ->set('estado', 'en_curso')
        ->set('cliente', (string) $this->cliente->id)
        ->assertSee('Tienda online')
        ->assertDontSee('Tienda física')
        ->assertDontSee('Tienda de otro cliente')
        ->call('limpiarFiltros')
        ->set('etiqueta', (string) $web->id)
        ->assertSee('Tienda online')
        ->assertDontSee('Tienda física');
});

test('el filtro "solo retrasados" muestra los proyectos activos fuera de plazo', function (): void {
    Proyecto::factory()->for($this->cliente)->estado(EstadoProyecto::EnCurso)->create(['nombre' => 'Atrasado', 'fecha_inicio' => now()->subMonth(), 'fecha_entrega' => now()->subDays(2)]);
    Proyecto::factory()->for($this->cliente)->estado(EstadoProyecto::EnCurso)->create(['nombre' => 'A tiempo', 'fecha_inicio' => now(), 'fecha_entrega' => now()->addWeek()]);

    Livewire::test('pages::proyectos.index')
        ->set('soloRetrasados', true)
        ->assertSee('Atrasado')
        ->assertDontSee('A tiempo');
});

test('cambia el estado desde la lista solo en proyectos propios', function (): void {
    $propio = Proyecto::factory()->for($this->cliente)->estado(EstadoProyecto::EnCurso)->create();
    $ajeno = Proyecto::factory()->estado(EstadoProyecto::EnCurso)->create();

    Livewire::test('pages::proyectos.index')->call('cambiarEstado', $propio->id, 'completado');
    expect($propio->fresh()->estado)->toBe(EstadoProyecto::Completado);

    Livewire::test('pages::proyectos.index')->call('cambiarEstado', $ajeno->id, 'completado')->assertForbidden();
    expect($ajeno->fresh()->estado)->toBe(EstadoProyecto::EnCurso);
});

test('crea un proyecto con etiquetas (muchos a muchos) y calcula el presupuesto en vivo', function (): void {
    $etiquetas = Etiqueta::factory(2)->for($this->usuario)->create();

    Livewire::test('pages::proyectos.form')
        ->set('cliente_id', (string) $this->cliente->id)
        ->set('nombre', 'Web corporativa')
        ->set('tarifa_hora', '50')
        ->set('horas_estimadas', '10')
        ->assertSee('530,00 €') // 500 + 21 % IVA − 15 % IRPF (empresa)
        ->set('etiquetas', $etiquetas->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect();

    $proyecto = Proyecto::query()->sole();
    expect($proyecto->user_id)->toBe($this->usuario->id)
        ->and($proyecto->etiquetas)->toHaveCount(2);
});

test('al editar, sync() deja exactamente las etiquetas marcadas', function (): void {
    [$a, $b] = Etiqueta::factory(2)->for($this->usuario)->create();
    $proyecto = Proyecto::factory()->for($this->cliente)->create();
    $proyecto->etiquetas()->attach([$a->id, $b->id]);

    Livewire::test('pages::proyectos.form', ['proyecto' => $proyecto])
        ->set('etiquetas', [(string) $b->id])
        ->call('guardar')
        ->assertHasNoErrors();

    expect($proyecto->fresh()->etiquetas->pluck('id')->all())->toBe([$b->id]);
});

test('no se puede usar el cliente ni las etiquetas de otra cuenta', function (): void {
    $clienteAjeno = Cliente::factory()->create();
    $etiquetaAjena = Etiqueta::factory()->create();

    Livewire::test('pages::proyectos.form')
        ->set('cliente_id', (string) $clienteAjeno->id)
        ->set('nombre', 'Intento')
        ->set('etiquetas', [(string) $etiquetaAjena->id])
        ->call('guardar')
        ->assertHasErrors(['cliente_id', 'etiquetas.0']);

    expect(Proyecto::query()->count())->toBe(0);
});

test('la entrega no puede ser anterior al inicio', function (): void {
    Livewire::test('pages::proyectos.form')
        ->set('cliente_id', (string) $this->cliente->id)
        ->set('nombre', 'Fechas')
        ->set('fecha_inicio', '2026-10-10')
        ->set('fecha_entrega', '2026-10-01')
        ->call('guardar')
        ->assertHasErrors(['fecha_entrega']);
});

test('el cliente rápido crea el cliente y el formulario lo selecciona', function (): void {
    Livewire::test('cliente-rapido')
        ->set('nombre', 'Nuevo Cliente')
        ->set('email', 'nuevo@cliente.test')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertDispatched('cliente-creado');

    $nuevo = Cliente::query()->where('email', 'nuevo@cliente.test')->sole();

    Livewire::test('pages::proyectos.form')
        ->dispatch('cliente-creado', id: $nuevo->id)
        ->assertSet('cliente_id', (string) $nuevo->id);
});

test('el tablero mueve proyectos entre columnas (arrastrar y soltar)', function (): void {
    $proyecto = Proyecto::factory()->for($this->cliente)->estado(EstadoProyecto::Propuesta)->create(['nombre' => 'Kanban']);
    $ajeno = Proyecto::factory()->estado(EstadoProyecto::Propuesta)->create();

    Livewire::test('pages::proyectos.tablero')
        ->assertSee('Kanban')
        ->call('mover', $proyecto->id, 'en_curso');
    expect($proyecto->fresh()->estado)->toBe(EstadoProyecto::EnCurso);

    Livewire::test('pages::proyectos.tablero')->call('mover', $ajeno->id, 'en_curso')->assertForbidden();
});

test('la ficha del proyecto muestra el presupuesto y se puede borrar', function (): void {
    $proyecto = Proyecto::factory()->for($this->cliente)->create(['tarifa_hora' => 50, 'horas_estimadas' => 10]);

    $this->get(route('proyectos.show', $proyecto))->assertOk()->assertSee('530,00 €');

    Livewire::test('pages::proyectos.show', ['proyecto' => $proyecto])->call('eliminar')->assertRedirect(route('proyectos.index'));
    expect(Proyecto::query()->count())->toBe(0);
});

test('no puede ver proyectos de otro usuario', function (): void {
    $ajeno = Proyecto::factory()->create();

    $this->get(route('proyectos.show', $ajeno))->assertForbidden();
    $this->get(route('proyectos.edit', $ajeno))->assertForbidden();
});
