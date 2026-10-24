<?php

namespace App\Mail;

use App\Models\Gestante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GestanteMensagemMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Gestante $gestante,
        public string $assunto,
        public string $corpo,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->assunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.gestante-mensagem',
        );
    }
}
