<?php

namespace App\Services\Integrations;

use Illuminate\Support\Str;

class WebhookPayloadFormatter
{
    public function isSlackIncomingWebhook(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && Str::endsWith(Str::lower($host), 'hooks.slack.com');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(string $url, string $event, array $payload, string $occurredAt): string
    {
        if ($this->isSlackIncomingWebhook($url)) {
            return json_encode([
                'text' => $this->slackText($event, $payload, $occurredAt),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'event' => $event,
            'occurred_at' => $occurredAt,
            'data' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function slackText(string $event, array $payload, string $occurredAt): string
    {
        $lines = [
            '*Phoenix* · `'.$event.'`',
            '_'.$occurredAt.'_',
            '',
        ];

        if ($event === 'webhook.test') {
            $lines[] = (string) ($payload['message'] ?? 'Prueba de entrega Phoenix');

            return implode("\n", $lines);
        }

        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $label = ucfirst(str_replace('_', ' ', (string) $key));
                $lines[] = '• *'.$label.'*: '.($value === null ? '—' : (string) $value);
            }
        }

        if (count($lines) === 3) {
            $lines[] = '_(sin datos adicionales)_';
        }

        return implode("\n", $lines);
    }
}
