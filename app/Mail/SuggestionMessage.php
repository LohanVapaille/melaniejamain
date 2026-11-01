<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SuggestionMessage extends Mailable
{
    public function __construct(
        public string $senderEmail,
        public string $idea,
        public string $language,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // « Répondre » dans la messagerie écrit directement au visiteur.
            replyTo: [new Address($this->senderEmail)],
            subject: 'Du droit aux étoiles | Nouvelle suggestion de sujet',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.suggestion',
            text: 'emails.suggestion-text',
        );
    }
}
