<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResearcherRegistrationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $researcherName, public string $reference) {}

    public function envelope(): Envelope { return new Envelope(subject: 'Demande de création de compte chercheur reçue'); }

    public function content(): Content { return new Content(view: 'emails.researcher-registration-received'); }
}