<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessage extends Mailable
{
    public function __construct(
        public string $senderEmail,
        public string $contactSubject,
        public string $contactMessage,
        public string $language,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // « Répondre » dans la messagerie écrit directement au visiteur.
            replyTo: [new Address($this->senderEmail)],
            subject: 'Du droit aux étoiles ' . $this->contactSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            text: 'emails.contact-text',
        );
    }
}
