<?php

use App\Enums\TipoCliente;
use App\Services\CalculadoraPresupuesto;
use App\ValueObjects\Presupuesto;

test('a una empresa se le aplica IVA y retención de IRPF', function (): void {
    $p = (new CalculadoraPresupuesto)->calcular(50, 10, TipoCliente::Empresa);

    expect($p->baseCentimos)->toBe(50_000)
        ->and($p->ivaCentimos)->toBe(10_500)
        ->and($p->irpfCentimos)->toBe(7_500)
        ->and($p->totalCentimos())->toBe(53_000);
});

test('a un particular no se le retiene IRPF', function (): void {
    $p = (new CalculadoraPresupuesto)->calcular(50, 10, TipoCliente::Particular);

    expect($p->irpfCentimos)->toBe(0)
        ->and($p->totalCentimos())->toBe(60_500);
});

test('trabaja en céntimos para evitar errores de redondeo', function (): void {
    $p = (new CalculadoraPresupuesto)->calcular(33.33, 3, TipoCliente::Autonomo);

    expect($p->baseCentimos)->toBe(9_999)
        ->and($p->total())->toBe('105,99 €');
});

test('rechaza valores negativos', function (): void {
    (new CalculadoraPresupuesto)->calcular(-1, 10, TipoCliente::Empresa);
})->throws(InvalidArgumentException::class);

test('formatea importes al estilo español', function (): void {
    expect(Presupuesto::formatear(123456))->toBe('1.234,56 €');
});

test('calcula impuestos a partir de una base en céntimos (facturas)', function (): void {
    $p = (new CalculadoraPresupuesto)->desdeBase(60_000, TipoCliente::Empresa);

    expect($p->totalCentimos())->toBe(63_600);
});
