<?php

namespace App\Services\Workflow;

use App\Models\Routine;
use App\Models\User;

class WorkflowEmailBodyRenderer
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function render(Routine $routine, array $config, ?User $recipient = null): string
    {
        $routine->loadMissing([
            'asset.catalogItem',
            'asset.activeClientAssignment.client',
            'routineType',
            'latestExecution',
            'assignee',
            'creator',
        ]);

        $plain = (string) ($config['message'] ?? '');
        $html = (string) ($config['body_html'] ?? '');
        $asHtml = $html !== '';
        $source = $asHtml ? $html : $plain;

        $replacements = $this->tokenMap($routine, $recipient, $asHtml);

        return str_replace(array_keys($replacements), array_values($replacements), $source);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function subject(Routine $routine, array $config, ?User $recipient = null): string
    {
        $routine->loadMissing(['routineType', 'asset.catalogItem', 'assignee', 'creator']);
        $subject = (string) ($config['subject'] ?? 'Phoenix — Servicio #'.$routine->id);

        return str_replace(
            array_keys($this->tokenMap($routine, $recipient, false)),
            array_values($this->tokenMap($routine, $recipient, false)),
            $subject,
        );
    }

    /**
     * @return array<string, string>
     */
    private function tokenMap(Routine $routine, ?User $recipient, bool $asHtml): array
    {
        $execution = $routine->latestExecution;
        $performer = $execution?->performer ?? $routine->assignee;
        $assetTag = (string) ($routine->asset?->tag ?? '—');
        $assetName = (string) ($routine->asset?->catalogItem?->name ?: $routine->asset?->tag ?: '—');

        return [
            '{routine.id}' => (string) $routine->id,
            '{routine.code}' => (string) ($routine->code ?? $routine->id),
            '{routine_type.name}' => (string) ($routine->routineType?->name ?? '—'),
            '{asset.tag}' => $assetTag,
            '{asset.name}' => $assetName,
            '{client.name}' => (string) (
                $routine->asset?->activeClientAssignment?->client?->trade_name
                ?: $routine->asset?->activeClientAssignment?->client?->legal_name
                ?: '—'
            ),
            '{user.name}' => (string) ($recipient?->name ?? $performer?->name ?? '—'),
            '{routine.tasks_detail}' => $this->formatTasksDetail($execution?->responses, $asHtml),
        ];
    }

    /**
     * @param  mixed  $responses
     */
    private function formatTasksDetail(mixed $responses, bool $asHtml): string
    {
        if (! is_array($responses) || $responses === []) {
            return '—';
        }

        $lines = [];
        foreach ($responses as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $label = (string) $key;
                $text = (string) ($value ?? '—');
                if ($asHtml) {
                    $lines[] = sprintf(
                        '<li><strong>%s:</strong> %s</li>',
                        e($label),
                        e($text),
                    );
                } else {
                    $lines[] = sprintf('%s: %s', $label, $text);
                }
            }
        }

        if ($lines === []) {
            return '—';
        }

        return $asHtml ? '<ul>'.implode('', $lines).'</ul>' : implode("\n", $lines);
    }
}
