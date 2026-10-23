<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Code that proves a (pending) Gmail sender account can deliver email. */
class SmtpSetupVerificationMail extends Mailable
{
    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
        public readonly string $senderEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'LSPDMS mailing setup verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smtp-setup-verification',
            text: 'emails.smtp-setup-verification-text',
            with: [
                'code' => $this->code,
                'minutes' => $this->minutes,
                'senderEmail' => $this->senderEmail,
            ],
        );
    }
}
