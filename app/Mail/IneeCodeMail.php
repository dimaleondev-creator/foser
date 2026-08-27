<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IneeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $studentName, public string $inee) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code INEE - Portail FOSER');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.inee-code');
    }
}