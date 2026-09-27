<?php

use App\Enums\EstadoFactura;
use App\Enums\TipoCliente;
use App\Mail\FacturaEnviada;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\FacturaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->usuario = User::factory()->conDatosFiscales()->create();
    $this->cliente = Cliente::factory()->for($this->usuario)->tipo(TipoCliente::Empresa)->create(['nombre' => 'Estudio Sur']);
    $this->servicio = app(FacturaService::class);
    $this->actingAs($this->usuario);
});

function borradorDe(User $usuario, Cliente $cliente, string $concepto = 'Trabajo'): Factura
{
    return app(FacturaService::class)->guardarBorrador($usuario, [
        'cliente_id' => $cliente->id, 'concepto' => $concepto, 'dias_pago' => 30,
    ], [['descripcion' => 'Horas', 'cantidad' => 10, 'precio' => 50]]);
}

test('crea un borrador con varias líneas y calcula los importes', function (): void {
    Livewire::test('pages::facturas.form')
        ->set('cliente_id', (string) $this->cliente->id)
        ->set('concepto', 'Desarrollo web')
        ->set('lineas.0.descripcion', 'Diseño')
        ->set('lineas.0.cantidad', '10')
        ->set('lineas.0.precio', '50')
        ->call('anadirLinea')
        ->set('lineas.1.descripcion', 'Hosting')
        ->set('lineas.1.cantidad', '1')
        ->set('lineas.1.precio', '100')
        ->assertSee('636,00 €') // 600 + 126 IVA − 90 IRPF
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect();

    $factura = Factura::query()->sole();
    expect($factura->estado)->toBe(EstadoFactura::Borrador)
        ->and($factura->numero)->toBeNull()
        ->and($factura->lineas)->toHaveCount(2)
        ->and($factura->total_centimos)->toBe(63_600);
});

test('valida cada línea por separado', function (): void {
    Livewire::test('pages::facturas.form')
        ->set('cliente_id', (string) $this->cliente->id)
        ->set('concepto', 'Algo')
        ->set('lineas.0.descripcion', '')
        ->set('lineas.0.cantidad', '0')
        ->call('guardar')
        ->assertHasErrors(['lineas.0.descripcion', 'lineas.0.cantidad']);
});

test('no se puede facturar al cliente ni al proyecto de otra cuenta', function (): void {
    $ajeno = Cliente::factory()->create();
    $proyectoAjeno = Proyecto::factory()->create();

    Livewire::test('pages::facturas.form')
        ->set('cliente_id', (string) $ajeno->id)
        ->set('concepto', 'Intento')
        ->set('lineas.0.descripcion', 'x')
        ->call('guardar')
        ->assertHasErrors(['cliente_id']);

    Livewire::test('pages::facturas.form')
        ->set('cliente_id', (string) $this->cliente->id)
        ->set('proyecto_id', (string) $proyectoAjeno->id)
        ->set('concepto', 'Intento')
        ->set('lineas.0.descripcion', 'x')
        ->call('guardar')
        ->assertHasErrors(['proyecto_id']);
});

test('facturar un proyecto rellena el formulario', function (): void {
    $proyecto = Proyecto::factory()->for($this->cliente)->create(['nombre' => 'Tienda online', 'horas_estimadas' => 12, 'tarifa_hora' => 40]);

    $this->get(route('facturas.create', ['proyecto' => $proyecto->id]))
        ->assertOk()
        ->assertSee('Tienda online');
});

test('al emitir recibe número correlativo y fecha de vencimiento', function (): void {
    $primera = borradorDe($this->usuario, $this->cliente);
    $segunda = borradorDe($this->usuario, $this->cliente);

    Livewire::test('pages::facturas.show', ['factura' => $primera])->call('emitir');
    Livewire::test('pages::facturas.show', ['factura' => $segunda])->call('emitir');

    $anio = now()->year;
    expect($primera->fresh()->numero)->toBe("FF-{$anio}-0001")
        ->and($segunda->fresh()->numero)->toBe("FF-{$anio}-0002")
        ->and($primera->fresh()->estado)->toBe(EstadoFactura::Enviada)
        ->and($primera->fresh()->fecha_vencimiento->toDateString())->toBe(now()->addDays(30)->toDateString());
});

test('cada usuario tiene su propia numeración y cada año empieza de cero', function (): void {
    $otro = User::factory()->create();
    $clienteOtro = Cliente::factory()->for($otro)->create();

    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente), CarbonImmutable::parse('2025-12-30'));
    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente), CarbonImmutable::parse('2026-01-02'));
    $deOtro = $this->servicio->emitir(borradorDe($otro, $clienteOtro), CarbonImmutable::parse('2026-01-05'));

    expect($this->usuario->facturas()->orderBy('id')->pluck('numero')->all())->toBe(['FF-2025-0001', 'FF-2026-0001'])
        ->and($deOtro->numero)->toBe('FF-2026-0001');
});

test('no se puede emitir sin datos fiscales', function (): void {
    $sinDatos = User::factory()->create();
    $factura = borradorDe($sinDatos, Cliente::factory()->for($sinDatos)->create());

    Livewire::actingAs($sinDatos)
        ->test('pages::facturas.show', ['factura' => $factura])
        ->call('emitir')
        ->assertRedirect(route('fiscal.edit'));

    expect($factura->fresh()->estado)->toBe(EstadoFactura::Borrador);
});

test('una factura emitida ya no se puede editar, borrar ni volver a emitir', function (): void {
    $factura = $this->servicio->emitir(borradorDe($this->usuario, $this->cliente));

    $this->get(route('facturas.edit', $factura))->assertForbidden();
    Livewire::test('pages::facturas.show', ['factura' => $factura])->call('eliminar')->assertForbidden();
    Livewire::test('pages::facturas.show', ['factura' => $factura])->call('emitir')->assertForbidden();
});

test('un borrador se puede editar y borrar', function (): void {
    $factura = borradorDe($this->usuario, $this->cliente);

    Livewire::test('pages::facturas.form', ['factura' => $factura])
        ->assertSet('concepto', 'Trabajo')
        ->set('concepto', 'Nuevo concepto')
        ->call('guardar')
        ->assertHasNoErrors();
    expect($factura->fresh()->concepto)->toBe('Nuevo concepto');

    Livewire::test('pages::facturas.show', ['factura' => $factura])->call('eliminar')->assertRedirect(route('facturas.index'));
    expect(Factura::query()->count())->toBe(0);
});

test('solo se puede cobrar una factura enviada', function (): void {
    $factura = borradorDe($this->usuario, $this->cliente);
    Livewire::test('pages::facturas.show', ['factura' => $factura])->call('pagar')->assertForbidden();

    $this->servicio->emitir($factura);
    Livewire::test('pages::facturas.show', ['factura' => $factura])->call('pagar');

    expect($factura->fresh()->estado)->toBe(EstadoFactura::Pagada)
        ->and($factura->fresh()->pagada_en)->not->toBeNull();
});

test('una factura enviada fuera de plazo se considera vencida', function (): void {
    $factura = $this->servicio->emitir(borradorDe($this->usuario, $this->cliente), now()->subDays(45));

    expect($factura->estaVencida())->toBeTrue()
        ->and($factura->diasVencida())->toBe(15)
        ->and(Factura::query()->vencidas()->count())->toBe(1);
});

test('envía la factura por email al cliente (Mailable)', function (): void {
    Mail::fake();
    $factura = $this->servicio->emitir(borradorDe($this->usuario, $this->cliente));

    Livewire::test('pages::facturas.show', ['factura' => $factura])->call('enviarEmail');

    Mail::assertSent(FacturaEnviada::class, fn (FacturaEnviada $mail) => $mail->hasTo($this->cliente->email) && ! $mail->recordatorio);
});

test('la tarea programada envía recordatorios de las facturas vencidas', function (): void {
    Mail::fake();
    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente), now()->subDays(60));
    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente), now());

    Sleep::fake();

    $this->artisan('facturas:recordatorios')->assertSuccessful();

    Mail::assertSent(FacturaEnviada::class, 1);
});

test('no puede ver facturas de otro usuario', function (): void {
    $otro = User::factory()->create();
    $ajena = borradorDe($otro, Cliente::factory()->for($otro)->create());

    $this->get(route('facturas.show', $ajena))->assertForbidden();
});

test('las pestañas filtran por estado, incluida la de vencidas, y se busca por cliente', function (): void {
    borradorDe($this->usuario, $this->cliente, 'Borrador pendiente');
    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente, 'Factura al día'));
    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente, 'Factura atrasada'), now()->subDays(60));

    Livewire::test('pages::facturas.index')
        ->assertSee('Borrador pendiente')
        ->call('cambiarPestana', 'vencida')
        ->assertSee('Factura atrasada')
        ->assertDontSee('Factura al día')
        ->call('cambiarPestana', 'todas')
        ->set('buscar', 'sur')
        ->assertSee('Factura al día');
});

test('registra el cobro desde la lista y no permite cobrar facturas ajenas', function (): void {
    $propia = $this->servicio->emitir(borradorDe($this->usuario, $this->cliente));
    $otro = User::factory()->create();
    $ajena = $this->servicio->emitir(borradorDe($otro, Cliente::factory()->for($otro)->create()));

    Livewire::test('pages::facturas.index')->call('cobrar', $propia->id);
    expect($propia->fresh()->estado)->toBe(EstadoFactura::Pagada);

    Livewire::test('pages::facturas.index')->call('cobrar', $ajena->id)->assertForbidden();
});

test('exporta las facturas a CSV', function (): void {
    $this->servicio->emitir(borradorDe($this->usuario, $this->cliente, 'Exportable'));

    Livewire::test('pages::facturas.index')->call('exportar')->assertFileDownloaded('facturas-'.now()->format('Y-m-d').'.csv');
});
