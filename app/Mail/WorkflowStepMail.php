<?php

namespace App\Mail;

use App\Models\Routine;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowStepMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Routine $routine,
        public string $mailSubject,
        public string $mailMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        $url = rtrim(config('app.url'), '/').'/app/routines/'.$this->routine->id;

        return new Content(
            text: 'mail.workflow-step',
            with: [
                'routineId' => $this->routine->id,
                'typeName' => $this->routine->routineType?->name ?? 'Rutina',
                'assetTag' => $this->routine->asset?->tag ?? '—',
                'body' => $this->mailMessage,
                'url' => $url,
            ],
        );
    }
}
