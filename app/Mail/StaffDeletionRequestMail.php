<?php

namespace App\Mail;

use App\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the gym admins and the Super Admin that a staff user asked, in the staff app, for their account to be deleted. */
class StaffDeletionRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $requestId,
        public string $staffName,
        public string $staffEmail,
        public string $gymName,
        public string $role,
        public ?string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Account deletion request: {$this->staffName} ({$this->gymName})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.staff-deletion-request', with: [
            'platformName' => PlatformSetting::get('platform_name'),
            'roleLabel' => $this->role === 'gym_admin' ? 'Gym admin' : 'Trainer',
        ]);
    }
}
