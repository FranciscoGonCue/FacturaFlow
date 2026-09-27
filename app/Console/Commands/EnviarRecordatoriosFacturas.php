<?php

namespace App\Console\Commands;

use App\Mail\FacturaEnviada;
use App\Models\Factura;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Tarea programada: envía un recordatorio por email de cada factura vencida.
 * Se ejecuta cada día a las 9:00 (ver routes/console.php) o a mano con:
 *   php artisan facturas:recordatorios
 */
#[Signature('facturas:recordatorios')]
#[Description('Envía un email de recordatorio a los clientes con facturas vencidas')]
class EnviarRecordatoriosFacturas extends Command
{
    public function handle(): int
    {
        $vencidas = Factura::query()->vencidas()->with(['cliente', 'user', 'lineas'])->get();

        foreach ($vencidas as $factura) {
            Mail::to($factura->cliente->email)->send(new FacturaEnviada($factura, recordatorio: true));
            $this->line("  → {$factura->numero} · {$factura->cliente->email}");
        }

        $this->info("Recordatorios enviados: {$vencidas->count()}");

        return self::SUCCESS;
    }
}
