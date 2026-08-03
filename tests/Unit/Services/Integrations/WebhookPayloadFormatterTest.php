<?php

namespace Tests\Unit\Services\Integrations;

use App\Services\Integrations\WebhookPayloadFormatter;
use PHPUnit\Framework\TestCase;

class WebhookPayloadFormatterTest extends TestCase
{
    public function test_slack_urls_receive_text_payload(): void
    {
        $formatter = new WebhookPayloadFormatter;

        $body = $formatter->encode(
            'https://hooks.slack.com/services/T00/B00/secret',
            'webhook.test',
            ['message' => 'Hola Slack'],
            '2026-08-02T12:00:00+00:00',
        );

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('text', $decoded);
        $this->assertStringContainsString('Hola Slack', $decoded['text']);
        $this->assertStringContainsString('webhook.test', $decoded['text']);
        $this->assertArrayNotHasKey('event', $decoded);
    }

    public function test_generic_urls_keep_phoenix_envelope(): void
    {
        $formatter = new WebhookPayloadFormatter;

        $body = $formatter->encode(
            'https://webhook.site/test',
            'routine.validated',
            ['routine_id' => 9],
            '2026-08-02T12:00:00+00:00',
        );

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('routine.validated', $decoded['event']);
        $this->assertSame(9, $decoded['data']['routine_id']);
    }
}
