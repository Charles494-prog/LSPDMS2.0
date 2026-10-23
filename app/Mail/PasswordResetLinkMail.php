<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Signed password reset link. Sent synchronously so failures surface. */
class PasswordResetLinkMail extends Mailable
{
    public function __construct(public readonly string $url, public readonly int $expiresIn) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your LSPDMS password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset', with: [
            'url' => $this->url,
            'expiresIn' => $this->expiresIn,
        ]);
    }
}
