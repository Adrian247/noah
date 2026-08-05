<?php

namespace App\Mail;

use App\Models\Routine;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoutinePendingValidationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Routine $routine) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Phoenix — Servicio pendiente de validación #'.$this->routine->id,
        );
    }

    public function content(): Content
    {
        $assetTag = $this->routine->asset?->tag ?? '—';
        $typeName = $this->routine->routineType?->name ?? 'Servicio';
        $url = rtrim(config('app.url'), '/').'/app/services/'.$this->routine->id;

        return new Content(
            text: 'mail.routine-pending-validation',
            with: [
                'routineId' => $this->routine->id,
                'typeName' => $typeName,
                'assetTag' => $assetTag,
                'url' => $url,
            ],
        );
    }
}
