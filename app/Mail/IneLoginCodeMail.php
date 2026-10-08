<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IneLoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $studentName, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion INE - Portail FOSER');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ine-login-code');
    }
}