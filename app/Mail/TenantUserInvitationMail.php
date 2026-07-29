<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantUserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $email,
        public string $plainPassword,
        public Company $company,
        public string $roleLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Phoenix — Acceso a '.$this->company->name,
        );
    }

    public function content(): Content
    {
        $loginUrl = rtrim((string) config('app.url'), '/').'/login';

        return new Content(
            text: 'mail.tenant-user-invitation',
            with: [
                'recipientName' => $this->recipientName,
                'email' => $this->email,
                'plainPassword' => $this->plainPassword,
                'companyName' => $this->company->name,
                'roleLabel' => $this->roleLabel,
                'loginUrl' => $loginUrl,
            ],
        );
    }
}
