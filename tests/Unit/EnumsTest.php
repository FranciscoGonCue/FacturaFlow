<?php

use App\Contracts\Etiquetable;
use App\Enums\EstadoFactura;
use App\Enums\EstadoProyecto;
use App\Enums\Rol;
use App\Enums\TipoCliente;

test('todos los casos de los enums tienen etiqueta y color', function (Etiquetable $caso): void {
    expect($caso->label())->not->toBeEmpty()
        ->and($caso->color())->toBeIn(['zinc', 'red', 'orange', 'amber', 'lime', 'emerald', 'teal', 'cyan', 'sky', 'indigo', 'violet', 'pink']);
})->with([...EstadoProyecto::cases(), ...TipoCliente::cases(), ...EstadoFactura::cases(), ...Rol::cases()]);

test('solo el estado completado deja de ser activo', function (): void {
    expect(EstadoProyecto::activos())->toBe([
        EstadoProyecto::Propuesta,
        EstadoProyecto::EnCurso,
        EstadoProyecto::Pausado,
    ]);
});

test('solo los particulares están exentos de IRPF', function (): void {
    expect(TipoCliente::Empresa->aplicaRetencionIrpf())->toBeTrue()
        ->and(TipoCliente::Autonomo->aplicaRetencionIrpf())->toBeTrue()
        ->and(TipoCliente::Particular->aplicaRetencionIrpf())->toBeFalse();
});

test('solo los borradores de factura son editables', function (): void {
    expect(EstadoFactura::Borrador->esEditable())->toBeTrue()
        ->and(EstadoFactura::Enviada->esEditable())->toBeFalse()
        ->and(EstadoFactura::Pagada->esEditable())->toBeFalse();
});
