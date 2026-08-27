<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResearcherAccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $researcherName) {}

    public function envelope(): Envelope { return new Envelope(subject: 'Votre compte chercheur a été approuvé'); }

    public function content(): Content { return new Content(view: 'emails.researcher-account-approved'); }
}