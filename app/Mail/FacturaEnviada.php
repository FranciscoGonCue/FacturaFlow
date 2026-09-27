<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email que recibe el CLIENTE con su factura.
 * Con "recordatorio = true" se usa como aviso de factura vencida (tarea programada).
 */
class FacturaEnviada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Factura $factura,
        public bool $recordatorio = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->recordatorio
                ? __('Reminder: invoice :number is overdue', ['number' => $this->factura->numero])
                : __('Invoice :number from :name', ['number' => $this->factura->numero, 'name' => $this->factura->user->name]),
            replyTo: [$this->factura->user->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.factura-enviada');
    }
}
