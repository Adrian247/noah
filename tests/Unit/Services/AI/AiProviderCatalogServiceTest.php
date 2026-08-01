<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AiPlatformSettingsService;
use App\Services\AI\AiProviderCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_catalog_is_always_ok(): void
    {
        $result = app(AiProviderCatalogService::class)->listModels('local');

        $this->assertTrue($result['ok']);
        $this->assertSame('local', $result['provider']);
        $this->assertNotEmpty($result['models']);
    }

    public function test_google_catalog_lists_models_when_api_ok(): void
    {
        config(['phoenix.ai.google.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.0-flash',
                        'displayName' => 'Gemini 2.0 Flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                    [
                        'name' => 'models/embed-only',
                        'supportedGenerationMethods' => ['embedContent'],
                    ],
                ],
            ], 200),
        ]);

        $result = app(AiProviderCatalogService::class)->listModels('google');

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['models']);
        $this->assertSame('gemini-2.0-flash', $result['models'][0]['id']);
    }

    public function test_google_catalog_fails_without_key(): void
    {
        config([
            'phoenix.ai.google.api_key' => null,
            'ai.providers.gemini.key' => null,
        ]);

        $result = app(AiProviderCatalogService::class)->listModels('google');

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['configured']);
    }

    public function test_platform_settings_stores_default_model_flag(): void
    {
        config(['phoenix.ai.google.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'models' => [[
                    'name' => 'models/gemini-2.0-flash',
                    'supportedGenerationMethods' => ['generateContent'],
                ]],
            ], 200),
        ]);

        $settings = app(AiPlatformSettingsService::class)->update([
            'provider' => 'google',
            'google_use_default' => true,
        ]);

        $this->assertTrue($settings['google_use_default']);
        $this->assertSame(config('phoenix.ai.google.model'), $settings['google_model']);
        $this->assertSame('google', $settings['provider']);
    }
}
