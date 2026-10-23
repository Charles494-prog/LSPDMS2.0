<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** One-time sign-in code (Require OTP). Sent synchronously so failures surface. */
class LoginVerificationCodeMail extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'LSPDMS verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verification-code', with: ['code' => $this->code]);
    }
}
