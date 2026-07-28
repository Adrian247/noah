<?php

namespace App\Services\Workflow;

use App\Models\Routine;
use App\Models\User;
use App\Models\WorkflowTransition;

class WorkflowEmailBodyRenderer
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function render(Routine $routine, array $config, ?User $recipient = null): string
    {
        $routine->loadMissing(['asset.activeClientAssignment.client', 'routineType', 'latestExecution', 'assignee', 'creator']);

        $plain = (string) ($config['message'] ?? '');
        $html = (string) ($config['body_html'] ?? '');
        $source = $html !== '' ? $html : $plain;

        $replacements = $this->tokenMap($routine, $recipient);

        return str_replace(array_keys($replacements), array_values($replacements), $source);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function subject(Routine $routine, array $config, ?User $recipient = null): string
    {
        $routine->loadMissing(['routineType']);
        $subject = (string) ($config['subject'] ?? 'Noah — Rutina #'.$routine->id);

        return str_replace(
            array_keys($this->tokenMap($routine, $recipient)),
            array_values($this->tokenMap($routine, $recipient)),
            $subject,
        );
    }

    /**
     * @return array<string, string>
     */
    private function tokenMap(Routine $routine, ?User $recipient): array
    {
        $execution = $routine->latestExecution;
        $performer = $execution?->performer ?? $routine->assignee;

        return [
            '{routine.id}' => (string) $routine->id,
            '{routine.code}' => (string) ($routine->code ?? $routine->id),
            '{routine_type.name}' => (string) ($routine->routineType?->name ?? '—'),
            '{asset.tag}' => (string) ($routine->asset?->tag ?? '—'),
            '{asset.name}' => (string) ($routine->asset?->name ?? '—'),
            '{client.name}' => (string) ($routine->asset?->activeClientAssignment?->client?->name ?? '—'),
            '{user.name}' => (string) ($recipient?->name ?? $performer?->name ?? '—'),
            '{routine.tasks_detail}' => $this->formatTasksDetail($execution?->responses),
        ];
    }

    /**
     * @param  mixed  $responses
     */
    private function formatTasksDetail(mixed $responses): string
    {
        if (! is_array($responses) || $responses === []) {
            return '—';
        }

        $lines = [];
        foreach ($responses as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $lines[] = sprintf('%s: %s', (string) $key, (string) ($value ?? '—'));
            }
        }

        return $lines === [] ? '—' : implode("\n", $lines);
    }
}
