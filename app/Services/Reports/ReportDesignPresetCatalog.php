<?php

namespace App\Services\Reports;

/**
 * Plantillas de diseño preestablecidas para informes PDF (page_settings + metadatos).
 */
final class ReportDesignPresetCatalog
{
    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     layout: string,
     *     swatch: array{primary: string, secondary: string, accent: string},
     *     page_settings: array<string, mixed>,
     * }>
     */
    public static function all(): array
    {
        return [
            self::presetPhoenixIndustrial(),
            self::presetCorporateNavy(),
            self::presetClinicalClean(),
            self::presetMinimalMono(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['id'] === $id) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return array{id: string, label: string, description: string, layout: string, swatch: array{primary: string, secondary: string, accent: string}, page_settings: array<string, mixed>}
     */
    private static function presetPhoenixIndustrial(): array
    {
        return [
            'id' => 'phoenix_industrial',
            'label' => 'Phoenix industrial',
            'description' => 'Portada oscura, acentos ámbar y tipografía clara. Ideal para mantenimiento y taller.',
            'layout' => 'full_form',
            'swatch' => ['primary' => '#f59e0b', 'secondary' => '#0f172a', 'accent' => '#d97706'],
            'page_settings' => self::mergeBaseSettings([
                'font_family' => 'roboto',
                'theme' => [
                    'preset_id' => 'phoenix_industrial',
                    'section_style' => 'card',
                    'colors' => [
                        'primary' => '#d97706',
                        'secondary' => '#0f172a',
                        'accent' => '#f59e0b',
                        'text' => '#0f172a',
                        'muted' => '#64748b',
                        'border' => '#e2e8f0',
                        'cover_bg' => '#0f172a',
                        'cover_text' => '#f8fafc',
                        'header_bg' => '#f8fafc',
                    ],
                ],
                'header' => ['enabled' => true, 'text' => '{{company}} · {{asset_tag}} · Rutina #{{routine_id}}'],
                'footer' => ['enabled' => true, 'text' => 'Documento confidencial · Generado por Phoenix'],
                'page_number' => ['enabled' => true, 'start_at' => 2],
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Informe de servicio técnico',
                    'subtitle' => '{{company}}',
                    'body' => 'Activo: **{{asset_tag}}**\n\nRutina de mantenimiento documentada con evidencias y validación.',
                    'show_date' => true,
                    'omit_header_footer' => true,
                    'logo_source' => 'client',
                    'client_id' => null,
                ],
                'typography' => ['title_pt' => 26, 'subtitle_pt' => 15, 'body_pt' => 11],
            ]),
        ];
    }

    /**
     * @return array{id: string, label: string, description: string, layout: string, swatch: array{primary: string, secondary: string, accent: string}, page_settings: array<string, mixed>}
     */
    private static function presetCorporateNavy(): array
    {
        return [
            'id' => 'corporate_navy',
            'label' => 'Corporativo azul',
            'description' => 'Líneas sobrias, azul marino y dorado. Presentación ejecutiva para clientes.',
            'layout' => 'full_form',
            'swatch' => ['primary' => '#1e3a5f', 'secondary' => '#0c1929', 'accent' => '#c9a227'],
            'page_settings' => self::mergeBaseSettings([
                'font_family' => 'source_sans',
                'theme' => [
                    'preset_id' => 'corporate_navy',
                    'section_style' => 'line',
                    'colors' => [
                        'primary' => '#1e3a5f',
                        'secondary' => '#0c1929',
                        'accent' => '#c9a227',
                        'text' => '#1e293b',
                        'muted' => '#475569',
                        'border' => '#cbd5e1',
                        'cover_bg' => '#1e3a5f',
                        'cover_text' => '#f8fafc',
                        'header_bg' => '#f1f5f9',
                    ],
                ],
                'header' => ['enabled' => true, 'text' => '{{company}} | Informe técnico #{{routine_id}}'],
                'footer' => ['enabled' => true, 'text' => '{{company}} · Página {{page}}'],
                'page_number' => ['enabled' => true, 'start_at' => 2],
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Reporte de mantenimiento',
                    'subtitle' => '{{company}}',
                    'body' => 'Equipo: {{asset_tag}}',
                    'show_date' => true,
                    'omit_header_footer' => true,
                ],
                'typography' => ['title_pt' => 24, 'subtitle_pt' => 14, 'body_pt' => 10.5],
            ]),
        ];
    }

    /**
     * @return array{id: string, label: string, description: string, layout: string, swatch: array{primary: string, secondary: string, accent: string}, page_settings: array<string, mixed>}
     */
    private static function presetClinicalClean(): array
    {
        return [
            'id' => 'clinical_clean',
            'label' => 'Clínico limpio',
            'description' => 'Mucho espacio en blanco, acento teal y bloques suaves. Lectura clara en campo.',
            'layout' => 'full_form',
            'swatch' => ['primary' => '#0d9488', 'secondary' => '#f0fdfa', 'accent' => '#14b8a6'],
            'page_settings' => self::mergeBaseSettings([
                'font_family' => 'source_sans',
                'theme' => [
                    'preset_id' => 'clinical_clean',
                    'section_style' => 'card',
                    'colors' => [
                        'primary' => '#0d9488',
                        'secondary' => '#134e4a',
                        'accent' => '#14b8a6',
                        'text' => '#134e4a',
                        'muted' => '#5eead4',
                        'border' => '#99f6e4',
                        'cover_bg' => '#f0fdfa',
                        'cover_text' => '#134e4a',
                        'header_bg' => '#ffffff',
                    ],
                ],
                'header' => ['enabled' => true, 'text' => 'Checklist · {{asset_tag}}'],
                'footer' => ['enabled' => false, 'text' => ''],
                'page_number' => ['enabled' => true, 'start_at' => 1],
                'cover_page' => [
                    'enabled' => true,
                    'title' => 'Inspección y registro',
                    'subtitle' => '{{company}}',
                    'body' => '',
                    'show_date' => true,
                    'omit_header_footer' => true,
                ],
                'typography' => ['title_pt' => 22, 'subtitle_pt' => 13, 'body_pt' => 11],
            ]),
        ];
    }

    /**
     * @return array{id: string, label: string, description: string, layout: string, swatch: array{primary: string, secondary: string, accent: string}, page_settings: array<string, mixed>}
     */
    private static function presetMinimalMono(): array
    {
        return [
            'id' => 'minimal_mono',
            'label' => 'Minimalista',
            'description' => 'Escala de grises, sin portada obligatoria. Máximo contenido por página.',
            'layout' => 'compact_form',
            'swatch' => ['primary' => '#374151', 'secondary' => '#f9fafb', 'accent' => '#111827'],
            'page_settings' => self::mergeBaseSettings([
                'font_family' => 'roboto',
                'theme' => [
                    'preset_id' => 'minimal_mono',
                    'section_style' => 'minimal',
                    'colors' => [
                        'primary' => '#374151',
                        'secondary' => '#111827',
                        'accent' => '#6b7280',
                        'text' => '#111827',
                        'muted' => '#6b7280',
                        'border' => '#e5e7eb',
                        'cover_bg' => '#ffffff',
                        'cover_text' => '#111827',
                        'header_bg' => '#ffffff',
                    ],
                ],
                'header' => ['enabled' => true, 'text' => '{{company}} — #{{routine_id}}'],
                'footer' => ['enabled' => true, 'text' => 'Phoenix'],
                'page_number' => ['enabled' => true, 'start_at' => 1],
                'cover_page' => [
                    'enabled' => false,
                    'title' => '',
                    'subtitle' => '',
                    'body' => '',
                    'show_date' => false,
                    'omit_header_footer' => true,
                ],
                'typography' => ['title_pt' => 20, 'subtitle_pt' => 13, 'body_pt' => 10],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function mergeBaseSettings(array $overrides): array
    {
        $base = [
            'size' => 'A4',
            'font_family' => 'roboto',
            'header' => ['enabled' => false, 'text' => ''],
            'footer' => ['enabled' => false, 'text' => ''],
            'page_number' => ['enabled' => false, 'start_at' => 1],
            'cover_page' => [
                'enabled' => false,
                'title' => 'Informe',
                'subtitle' => '{{company}}',
                'body' => '',
                'show_date' => true,
                'omit_header_footer' => true,
            ],
            'typography' => ['title_pt' => 22, 'subtitle_pt' => 16, 'body_pt' => 11],
        ];

        return array_replace_recursive($base, $overrides);
    }
}
