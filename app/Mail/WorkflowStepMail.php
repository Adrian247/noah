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
        $this->routine->loadMissing(['asset', 'routineType']);

        $url = rtrim(config('app.url'), '/').'/app/routines/'.$this->routine->id;
        $bodyText = trim(html_entity_decode(strip_tags($this->mailMessage), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return new Content(
            html: 'mail.workflow-step-html',
            text: 'mail.workflow-step',
            with: [
                'routineId' => $this->routine->id,
                'typeName' => $this->routine->routineType?->name ?? 'Rutina',
                'assetTag' => $this->routine->asset?->tag ?? '—',
                'body' => $this->mailMessage,
                'bodyText' => $bodyText,
                'url' => $url,
                'mailSubject' => $this->mailSubject,
            ],
        );
    }
}
