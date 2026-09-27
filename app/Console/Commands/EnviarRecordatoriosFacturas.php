<?php

namespace App\Console\Commands;

use App\Mail\FacturaEnviada;
use App\Models\Factura;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Tarea programada: envía un recordatorio por email de cada factura vencida.
 * Se ejecuta cada día a las 9:00 (ver routes/console.php) o a mano con:
 *   php artisan facturas:recordatorios
 */
#[Signature('facturas:recordatorios {--pausa=2 : Segundos de espera entre email y email}')]
#[Description('Envía un email de recordatorio a los clientes con facturas vencidas')]
class EnviarRecordatoriosFacturas extends Command
{
    public function handle(): int
    {
        $vencidas = Factura::query()->vencidas()->with(['cliente', 'user', 'lineas'])->get();
        $pausa = max(0, (int) $this->option('pausa'));
        $enviados = 0;

        foreach ($vencidas as $indice => $factura) {
            // Pausa entre envíos: los servidores SMTP (p. ej. Mailtrap gratis) limitan los emails por segundo.
            if ($indice > 0 && $pausa > 0) {
                Sleep::for($pausa)->seconds();
            }

            try {
                Mail::to($factura->cliente->email)->send(new FacturaEnviada($factura, recordatorio: true));
                $enviados++;
                $this->line("  → {$factura->numero} · {$factura->cliente->email}");
            } catch (Throwable $e) {
                // Si un email falla, se avisa y se sigue con los demás.
                report($e);
                $this->error("  ✗ {$factura->numero} · {$factura->cliente->email}: {$e->getMessage()}");
            }
        }

        $this->info("Recordatorios enviados: {$enviados} de {$vencidas->count()}");

        return $enviados === $vencidas->count() ? self::SUCCESS : self::FAILURE;
    }
}
