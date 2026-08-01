<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportDesignPresetCatalog;
use App\Services\Reports\ReportPresetApplier;
use Tests\TestCase;

class ReportPresetApplierTest extends TestCase
{
    public function test_catalog_exposes_four_presets_with_theme_colors(): void
    {
        $presets = ReportDesignPresetCatalog::all();

        $this->assertCount(4, $presets);
        $ids = array_column($presets, 'id');
        $this->assertContains('phoenix_industrial', $ids);
        $this->assertContains('corporate_navy', $ids);

        $industrial = ReportDesignPresetCatalog::find('phoenix_industrial');
        $this->assertNotNull($industrial);
        $this->assertSame('phoenix_industrial', $industrial['page_settings']['theme']['preset_id'] ?? null);
        $this->assertNotEmpty($industrial['page_settings']['theme']['colors']['primary'] ?? '');
    }

    public function test_components_from_schema_includes_sections_and_photos(): void
    {
        $applier = new ReportPresetApplier;
        $version = new \App\Models\FormVersion([
            'schema' => [
                'sections' => [
                    [
                        'title' => 'Datos generales',
                        'fields' => [
                            ['key' => 'placa', 'type' => 'text'],
                            ['key' => 'foto_lateral', 'type' => 'photo'],
                        ],
                    ],
                ],
            ],
        ]);

        $components = $applier->componentsFromFormVersion($version, 'full_form');

        $types = array_column($components, 'type');
        $this->assertContains('title', $types);
        $this->assertContains('subtitle', $types);
        $this->assertTrue(
            collect($components)->contains(fn (array $c) => $c['type'] === 'paragraph' && ($c['field'] ?? '') === 'placa'),
        );
        $this->assertTrue(
            collect($components)->contains(fn (array $c) => $c['type'] === 'image' && ($c['field'] ?? '') === 'foto_lateral'),
        );
        $this->assertTrue(
            collect($components)->contains(
                fn (array $c) => $c['type'] === 'paragraph'
                    && ($c['field'] ?? '') === 'placa'
                    && ($c['show_field_key'] ?? null) === false,
            ),
        );
    }

    public function test_theme_only_build_does_not_require_form(): void
    {
        $applier = new ReportPresetApplier;
        $built = $applier->build('phoenix_industrial', 1, null, 'theme_only');

        $this->assertSame([], $built['components']);
        $this->assertSame('phoenix_industrial', $built['page_settings']['theme']['preset_id'] ?? null);
        $this->assertStringNotContainsString('{{page}}', (string) ($built['page_settings']['footer']['text'] ?? ''));
    }

    public function test_catalog_footer_never_contains_page_placeholder(): void
    {
        foreach (ReportDesignPresetCatalog::all() as $preset) {
            $footer = (string) ($preset['page_settings']['footer']['text'] ?? '');
            $this->assertStringNotContainsString('{{page}}', $footer, $preset['id']);
        }
    }
}
