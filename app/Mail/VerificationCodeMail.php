<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $language;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, string $language = 'fr')
    {
        $this->code = $code;
        $this->language = $language;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Utiliser les traductions
        app()->setLocale($this->language);
        $subject = __('emails.verification.subject');

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: [
                'code' => $this->code,
                'language' => $this->language,
            ],
        );
    }
}