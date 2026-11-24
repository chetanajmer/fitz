<?php

namespace App\Mail;

use App\Models\PlatformSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The one-time code for signing in to the website by email. */
class LoginCodeMail extends Mailable
{
    public function __construct(public string $code, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your ".PlatformSetting::get('platform_name').' sign-in code');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.login-code', with: ['platformName' => PlatformSetting::get('platform_name')]);
    }
}
