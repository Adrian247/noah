<?php

namespace App\Support\Predictive;

use App\Models\CatalogItem;
use App\Models\EquipmentType;
use App\Models\OemEquipmentModel;
use App\Models\OemMaintenancePlan;
use App\Models\OemMaintenancePlanItem;

/**
 * Catálogo de modelos y planes de mantenimiento OEM (global).
 *
 * Se asocia al catálogo de equipos de cada tenant (`catalog_items.oem_equipment_model_id`)
 * para intervalos de servicio y clasificación. No es una pestaña del módulo de predicción:
 * la predicción usa rutinas aplicadas; el OEM aporta contexto de plan/clase.
 *
 * Exactitud del dato (`verified`):
 * - Modelos y capacidades tomados de fichas oficiales del OEM → `verified = true`.
 * - Intervalos Sandvik (250/500/1000/2000/4000 h) y Metso (250/1000/2000 h) → `verified = true`
 *   contra documento oficial del fabricante.
 * - Intervalos Epiroc: el OEM no publica la tabla de horas (solo kits por modelo vía representante);
 *   se siembra la convención de industria y queda `verified = false` hasta confirmar contra el
 *   manual del cliente.
 *
 * Nomenclatura BEV Epiroc: la serie vigente es **SG** ("Smart and Green"), no "Battery". Certiq
 * fue discontinuado; la telemetría vigente es Fleet+ sobre My Epiroc.
 */
final class OemCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function models(): array
    {
        return [
            ...self::epirocScooptrams(),
            ...self::epirocTrucks(),
            ...self::epirocDrillRigs(),
            ...self::sandvikLoaders(),
            ...self::sandvikTrucks(),
            ...self::sandvikDrillRigs(),
            ...self::metsoCrushers(),
            ...self::millsAndPlant(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function epirocScooptrams(): array
    {
        // Capacidades del listado oficial de diesel loaders. ST3.5 aparece como 6 t pese al nombre.
        $diesel = [
            'ST4' => 4.0,
            'ST3.5' => 6.0,
            'ST7' => 6.8,
            'ST7LP' => 7.0,
            'ST1030' => 10.0,
            'ST1030LP' => 10.0,
            'ST14 S' => 14.0,
            'ST18 S' => 17.5,
        ];
        // Serie SG = batería (antes "Battery"); EST1030 es tethered (cable).
        $battery = [
            'ST10 G' => 10.0,
            'ST14 SG' => 14.0,
            'ST18 SG' => 18.0,
        ];

        $models = [];
        foreach ($diesel as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Scooptram',
                'model' => $model,
                'equipment_class' => 'SCOOPTRAM',
                'application' => 'underground',
                'description' => 'Cargador frontal de bajo perfil (LHD) diésel para minería subterránea.',
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'drive' => '4x4 articulado',
                    'power_source' => 'diésel',
                    'typical_service_life_hours' => 25000,
                    'mid_life_rebuild_hours' => [12000, 14000],
                ],
                'source_url' => 'https://www.epiroc.com/en/products/loaders-and-trucks/diesel-loaders',
                'verified' => true,
            ];
        }
        foreach ($battery as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Scooptram SG',
                'model' => $model,
                'equipment_class' => 'SCOOPTRAM',
                'application' => 'underground',
                'description' => 'Cargador LHD a batería (serie Smart and Green). Sin motor diésel: el plan de PM excluye filtros y aceite de motor.',
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'drive' => '4x4 articulado',
                    'power_source' => 'batería',
                    'charge_interface' => 'CCS',
                ],
                'source_url' => 'https://www.epiroc.com/en/products/electrification-solutions/underground-electrification',
                'verified' => true,
            ];
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function epirocTrucks(): array
    {
        $diesel = [
            'MT22' => 22.0,
            'MT33' => 33.0,
            'MT431B' => 28.1,
            'MT436B' => 32.6,
            'MT436LP' => 32.6,
            'MT42 S' => 42.0,
            'MT54 S' => 54.0,
            'MT65 S' => 65.0,
            'MT66 S eDrive' => 66.0,
        ];
        $battery = [
            'MT42 SG' => 42.0,
            'MT42 SG Trolley' => 42.0,
        ];

        $models = [];
        foreach ($diesel as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Minetruck',
                'model' => $model,
                'equipment_class' => 'CAMION_BAJO_PERFIL',
                'application' => 'underground',
                'description' => 'Camión de acarreo de bajo perfil para rampa y galería.',
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'power_source' => str_contains($model, 'eDrive') ? 'diésel-eléctrico' : 'diésel',
                ],
                'source_url' => 'https://www.epiroc.com/en/products/loaders-and-trucks/underground-mining-trucks',
                'verified' => true,
            ];
        }
        foreach ($battery as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Minetruck SG',
                'model' => $model,
                'equipment_class' => 'CAMION_BAJO_PERFIL',
                'application' => 'underground',
                'description' => 'Camión de bajo perfil a batería (serie Smart and Green)'
                    .(str_contains($model, 'Trolley') ? ' con opción de trolley aéreo.' : '.'),
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'power_source' => str_contains($model, 'Trolley') ? 'batería+trolley' : 'batería',
                ],
                'source_url' => 'https://www.epiroc.com/en/products/electrification-solutions/underground-electrification',
                'verified' => true,
            ];
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function epirocDrillRigs(): array
    {
        $boomers = [
            'Boomer S1 L' => ['booms' => 1, 'desc' => 'Jumbo hidráulico de bajo perfil para galerías y túneles pequeños.'],
            'Boomer S2' => ['booms' => 2, 'desc' => 'Jumbo de 2 brazos para desarrollo de mina y túneles pequeños.'],
            'Boomer M2 D' => ['booms' => 2, 'desc' => 'Jumbo de control directo (DCS) de 2 brazos.'],
            'Boomer M20 S' => ['booms' => 2, 'desc' => 'Jumbo de nueva generación con brazos de hidráulica interna (sin mangueras externas).'],
            'Boomer M20 SG' => ['booms' => 2, 'desc' => 'Primer jumbo capaz de perforar parcialmente con batería (serie SG).'],
            'Boomer E2' => ['booms' => 2, 'desc' => 'Jumbo hidráulico para galerías medianas a grandes.'],
            'Boomer 282' => ['booms' => 2, 'desc' => 'Jumbo legacy de control hidráulico directo.'],
        ];

        $models = [];
        foreach ($boomers as $model => $meta) {
            $battery = str_ends_with($model, 'SG');
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Boomer',
                'model' => $model,
                'equipment_class' => 'JUMBO',
                'application' => 'underground',
                'description' => $meta['desc'],
                'specifications' => [
                    'booms' => $meta['booms'],
                    'power_source' => $battery
                        ? 'batería (perforación parcial) + diésel de traslado'
                        : 'diésel para traslado, eléctrico para barrenación',
                ],
                'source_url' => 'https://www.epiroc.com/en/products/drill-rigs/face-drill-rigs',
                'verified' => true,
            ];
        }

        foreach (['Simba S7', 'Simba M6', 'Simba E60 S', 'Simba E70 S'] as $model) {
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Simba',
                'model' => $model,
                'equipment_class' => 'JUMBO',
                'application' => 'underground',
                'description' => 'Taladro largo de producción (long hole).',
                'specifications' => ['power_source' => 'diésel para traslado, eléctrico para barrenación'],
                'source_url' => 'https://www.epiroc.com/en/products/drill-rigs/production-drill-rigs',
                'verified' => true,
            ];
        }

        foreach (['Boltec M10 S', 'Boltec E10 S'] as $model) {
            $models[] = [
                'manufacturer' => 'Epiroc',
                'family' => 'Boltec',
                'model' => $model,
                'equipment_class' => 'JUMBO',
                'application' => 'underground',
                'description' => 'Empernadora mecanizada para refuerzo de roca.',
                'specifications' => ['power_source' => 'diésel para traslado, eléctrico para barrenación'],
                'source_url' => 'https://www.epiroc.com/en/products/rock-reinforcement/rock-bolting-rigs',
                'verified' => true,
            ];
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sandvikLoaders(): array
    {
        $diesel = [
            'LH202' => 3.0,
            'LH203' => 3.5,
            'LH307' => 7.0,
            'LH410' => 10.0,
            'LH209L' => 9.6,
            'LH514' => 14.0,
            'LH515i' => 15.0,
            'LH517i' => 17.2,
            'LH621i' => 21.0,
        ];
        $electric = [
            'LH514iE' => ['payload' => 14.0, 'source' => 'eléctrico por cable'],
            'LH625iE' => ['payload' => 25.0, 'source' => 'eléctrico por cable'],
            'LH518iB' => ['payload' => 18.0, 'source' => 'batería'],
        ];

        $models = [];
        foreach ($diesel as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Sandvik',
                'family' => 'Toro / LH',
                'model' => $model,
                'equipment_class' => 'SCOOPTRAM',
                'application' => 'underground',
                'description' => 'Cargador LHD diésel; la serie i incluye telemetría y monitoreo a bordo (My Sandvik / Knowledge Box).',
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'drive' => '4x4 articulado',
                    'power_source' => 'diésel',
                    'onboard_monitoring' => str_contains($model, 'i'),
                    'hour_meter_types' => ['motor', 'tramming'],
                ],
                'source_url' => 'https://www.mining.sandvik/en/products/equipment/loaders/',
                'verified' => true,
            ];
        }
        foreach ($electric as $model => $meta) {
            $models[] = [
                'manufacturer' => 'Sandvik',
                'family' => 'Toro / LH',
                'model' => $model,
                'equipment_class' => 'SCOOPTRAM',
                'application' => 'underground',
                'description' => 'Cargador LHD '.$meta['source'].'.',
                'specifications' => [
                    'payload_tonnes' => $meta['payload'],
                    'drive' => '4x4 articulado',
                    'power_source' => $meta['source'],
                    'onboard_monitoring' => true,
                ],
                'source_url' => 'https://www.mining.sandvik/en/products/equipment/loaders/',
                'verified' => true,
            ];
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sandvikTrucks(): array
    {
        $diesel = [
            'TH545i' => 45.0,
            'TH551i' => 51.0,
            'TH663i' => 63.0,
        ];
        $battery = [
            'TH550B' => 50.0,
            'TH665B' => 65.0,
        ];

        $models = [];
        foreach ($diesel as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Sandvik',
                'family' => 'Toro / TH',
                'model' => $model,
                'equipment_class' => 'CAMION_BAJO_PERFIL',
                'application' => 'underground',
                'description' => 'Camión de bajo perfil para acarreo subterráneo.',
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'power_source' => 'diésel',
                    'onboard_monitoring' => true,
                ],
                'source_url' => 'https://www.mining.sandvik/en/products/equipment/trucks/',
                'verified' => true,
            ];
        }
        foreach ($battery as $model => $payload) {
            $models[] = [
                'manufacturer' => 'Sandvik',
                'family' => 'Toro / TH',
                'model' => $model,
                'equipment_class' => 'CAMION_BAJO_PERFIL',
                'application' => 'underground',
                'description' => 'Camión de bajo perfil a batería (BEV).',
                'specifications' => [
                    'payload_tonnes' => $payload,
                    'power_source' => 'batería',
                    'onboard_monitoring' => true,
                ],
                'source_url' => 'https://www.mining.sandvik/en/products/equipment/trucks/',
                'verified' => true,
            ];
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sandvikDrillRigs(): array
    {
        $rigs = [
            'DD312i' => ['booms' => 1, 'desc' => 'Jumbo de desarrollo; cobertura ~48 m².', 'coverage_m2' => 48],
            'DD321' => ['booms' => 2, 'desc' => 'Jumbo de desarrollo de dos brazos.', 'coverage_m2' => 49],
            'DD322i' => ['booms' => 2, 'desc' => 'Jumbo de desarrollo hidrostático.', 'coverage_m2' => 58],
            'DD422i' => ['booms' => 2, 'desc' => 'Jumbo de dos brazos con control SICA y telemetría.', 'coverage_m2' => 60],
            'DD422iE' => ['booms' => 2, 'desc' => 'Jumbo BEV de dos brazos.', 'coverage_m2' => 60],
            'DD423i' => ['booms' => 2, 'desc' => 'Jumbo de desarrollo de gran cobertura.', 'coverage_m2' => 81],
            'DL422i' => ['booms' => 1, 'desc' => 'Taladro largo (long hole) hasta 54 m.', 'coverage_m2' => null],
            'DL432i' => ['booms' => 1, 'desc' => 'Taladro largo para galerías compactas.', 'coverage_m2' => null],
            'DS412i' => ['booms' => 1, 'desc' => 'Empernadora para soporte de terreno.', 'coverage_m2' => null],
        ];

        return array_map(fn (string $model) => [
            'manufacturer' => 'Sandvik',
            'family' => 'DD / DL / DS',
            'model' => $model,
            'equipment_class' => 'JUMBO',
            'application' => 'underground',
            'description' => $rigs[$model]['desc'],
            'specifications' => array_filter([
                'booms' => $rigs[$model]['booms'],
                'coverage_m2' => $rigs[$model]['coverage_m2'],
                'onboard_monitoring' => str_contains($model, 'i'),
                'power_source' => str_ends_with($model, 'E') ? 'batería' : 'diésel/eléctrico',
                'hour_meter_types' => ['motor', 'tramming', 'percussion'],
            ], fn ($v) => $v !== null),
            'source_url' => 'https://www.mining.sandvik/en/products/equipment/underground-drill-rigs/',
            'verified' => true,
        ], array_keys($rigs));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function metsoCrushers(): array
    {
        // C110 aparece en la bitácora de Planta 4400; el resto es la serie C / HP / GP publicada.
        $jaw = ['C80', 'C96', 'C106', 'C110', 'C116', 'C130', 'C150'];
        $cone = ['HP100', 'HP200', 'HP300', 'HP400', 'HP500', 'GP100', 'GP200', 'GP300', 'GP550'];

        $models = [];
        foreach ($jaw as $model) {
            $models[] = [
                'manufacturer' => 'Metso',
                'family' => 'Nordberg C',
                'model' => $model,
                'equipment_class' => 'QUEBRADORA',
                'application' => 'plant',
                'description' => 'Quebradora de mandíbulas para trituración primaria.',
                'specifications' => [
                    'crusher_type' => 'mandíbulas',
                    'stage' => 'primaria',
                    'control_system' => 'Metso IC',
                ],
                'source_url' => 'https://www.metso.com/products/crushers/jaw-crushers/',
                'verified' => true,
            ];
        }
        foreach ($cone as $model) {
            $models[] = [
                'manufacturer' => 'Metso',
                'family' => str_starts_with($model, 'HP') ? 'Nordberg HP' : 'Nordberg GP',
                'model' => $model,
                'equipment_class' => 'QUEBRADORA',
                'application' => 'plant',
                'description' => 'Quebradora de cono para trituración secundaria y terciaria. Alarmas IC: A=alarma, W=advertencia, M=mensaje.',
                'specifications' => [
                    'crusher_type' => 'cono',
                    'stage' => 'secundaria/terciaria',
                    'control_system' => 'Metso IC70C',
                ],
                'source_url' => 'https://www.metso.com/products/crushers/cone-crushers/',
                'verified' => true,
            ];
        }

        return $models;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function millsAndPlant(): array
    {
        return [
            [
                'manufacturer' => 'Metso',
                'family' => 'Molino de bolas',
                'model' => 'Ball Mill (genérico)',
                'equipment_class' => 'MOLINO',
                'application' => 'plant',
                'description' => 'Molino de bolas de descarga por rebalse o rejilla para molienda fina.',
                'specifications' => ['mill_type' => 'bolas', 'drive' => 'piñón-corona'],
                'source_url' => 'https://www.metso.com/products/grinding-mills/',
                'verified' => false,
            ],
            [
                'manufacturer' => 'Metso',
                'family' => 'Molino SAG',
                'model' => 'SAG Mill (genérico)',
                'equipment_class' => 'MOLINO',
                'application' => 'plant',
                'description' => 'Molino semiautógeno para molienda primaria.',
                'specifications' => ['mill_type' => 'semiautógeno', 'drive' => 'piñón-corona'],
                'source_url' => 'https://www.metso.com/products/grinding-mills/',
                'verified' => false,
            ],
            [
                'manufacturer' => 'Outotec',
                'family' => 'Celda de flotación',
                'model' => 'TankCell (genérico)',
                'equipment_class' => 'CELDA_FLOTACION',
                'application' => 'plant',
                'description' => 'Celda mecánica de flotación con rotor y estator.',
                'specifications' => ['agitation' => 'mecánica'],
                'source_url' => 'https://www.metso.com/products/flotation-cells/',
                'verified' => false,
            ],
            [
                'manufacturer' => 'Outotec',
                'family' => 'Espesador',
                'model' => 'Thickener (genérico)',
                'equipment_class' => 'ESPESADOR',
                'application' => 'plant',
                'description' => 'Espesador de rastras para separación sólido-líquido. La falla mecánica suele ser sobrecarga de proceso (isla/dona), no fatiga.',
                'specifications' => ['rake_drive' => 'motorreductor con torquímetro'],
                'source_url' => 'https://www.metso.com/products/thickeners-and-clarifiers/',
                'verified' => false,
            ],
        ];
    }

    /**
     * Planes de mantenimiento por intervalos, uno por fabricante y clase.
     *
     * @return list<array<string, mixed>>
     */
    public static function plans(): array
    {
        return [
            [
                'manufacturer' => 'Epiroc',
                'equipment_class' => 'SCOOPTRAM',
                'name' => 'Servicio por horómetro 250/500/1000/2000 h',
                'notes' => 'Convención de industria: Epiroc no publica la tabla de horas (kits por modelo vía representante). Confirmar contra el manual del cliente. Para Scooptram SG (batería) omitir tareas de motor_diesel.',
                'source_url' => 'https://www.epiroc.com/en/products/parts-and-services/replacement-parts-and-kits/preventive-maintenance-kits',
                'verified' => false,
                'items' => self::mobileDieselPlanItems(),
            ],
            [
                'manufacturer' => 'Epiroc',
                'equipment_class' => 'CAMION_BAJO_PERFIL',
                'name' => 'Servicio por horómetro 250/500/1000/2000 h',
                'notes' => 'Convención de industria (Epiroc no publica intervalos numéricos). Énfasis en suspensión, tolva y frenos por la carga de rampa.',
                'source_url' => 'https://www.epiroc.com/en/products/parts-and-services/replacement-parts-and-kits/preventive-maintenance-kits',
                'verified' => false,
                'items' => array_merge(self::mobileDieselPlanItems(), [
                    ['interval_hours' => 500, 'task' => 'Inspección de tolva y cilindros de volteo', 'system' => 'hidraulico'],
                    ['interval_hours' => 1000, 'task' => 'Revisión de suspensión y bujes de articulación', 'system' => 'chasis'],
                ]),
            ],
            [
                'manufacturer' => 'Epiroc',
                'equipment_class' => 'JUMBO',
                'name' => 'Servicio de jumbo 250/500/1000 h',
                'notes' => 'Convención de industria. Añade percusión, rotación y viga de avance. Preferir horómetro de percusión para el desgaste del martillo.',
                'source_url' => 'https://www.epiroc.com/en/products/parts-and-services/replacement-parts-and-kits/preventive-maintenance-kits',
                'verified' => false,
                'items' => array_merge(self::mobileDieselPlanItems(), self::drillRigPlanItems()),
            ],
            [
                'manufacturer' => 'Sandvik',
                'equipment_class' => 'SCOOPTRAM',
                'name' => 'Servicio por horómetro 250/500/1000/2000/4000 h',
                'notes' => 'Cadencia confirmada por el planificador oficial Sandvik (250/500/1000/2000/4000 h). Ajustar a condiciones de operación.',
                'source_url' => 'https://www.mining.sandvik/en/products/kits-and-consumables/maintenance-kits/',
                'verified' => true,
                'items' => array_merge(self::mobileDieselPlanItems(), [
                    ['interval_hours' => 4000, 'task' => 'Inspección mayor de tren motriz, ejes y chasis articulado', 'system' => 'tren_motriz'],
                ]),
            ],
            [
                'manufacturer' => 'Sandvik',
                'equipment_class' => 'CAMION_BAJO_PERFIL',
                'name' => 'Servicio por horómetro 250/500/1000/2000/4000 h',
                'notes' => 'Cadencia Sandvik confirmada. Circuito de frenos tiene tanque, respiradero y filtros propios a 250/500 h.',
                'source_url' => 'https://www.mining.sandvik/en/products/kits-and-consumables/maintenance-kits/',
                'verified' => true,
                'items' => array_merge(self::mobileDieselPlanItems(), [
                    ['interval_hours' => 250, 'task' => 'Filtro de retorno del circuito de frenos', 'system' => 'frenos'],
                    ['interval_hours' => 500, 'task' => 'Filtro de alta presión de frenos y dirección', 'system' => 'frenos'],
                    ['interval_hours' => 4000, 'task' => 'Inspección mayor de tren motriz y tolva', 'system' => 'tren_motriz'],
                ]),
            ],
            [
                'manufacturer' => 'Sandvik',
                'equipment_class' => 'JUMBO',
                'name' => 'Servicio de jumbo 250/500/1000/2000 h',
                'notes' => 'Cadencia Sandvik. Separar horas de motor, tramming y percusión: el desgaste del martillo correlaciona con percusión.',
                'source_url' => 'https://www.mining.sandvik/en/products/kits-and-consumables/maintenance-kits/',
                'verified' => true,
                'items' => array_merge(self::mobileDieselPlanItems(), self::drillRigPlanItems()),
            ],
            [
                'manufacturer' => 'Metso',
                'equipment_class' => 'QUEBRADORA',
                'name' => 'Mantenimiento de trituración 8/40/250/1000/2000 h',
                'notes' => 'Kits oficiales Metso a 250/1000/2000 h (C, HP, GP, MX, NP). Los intervalos 8/40 h son rondas de operación. Aceite de cono: típico cada 2000 h (o top-up con filtración offline).',
                'source_url' => 'https://www.metso.com/globalassets/saleshub/documents---episerver/metso-leaflet-maintenance-kit-3601-en.pdf',
                'verified' => true,
                'items' => [
                    ['interval_hours' => 8, 'task' => 'Ronda de lubricación y nivel de aceite; temperatura de chumaceras', 'system' => 'lubricacion'],
                    ['interval_hours' => 8, 'task' => 'Revisión de ruidos anormales y acumulación bajo trituradora', 'system' => 'trituracion'],
                    ['interval_hours' => 40, 'task' => 'Inspección de desgaste de muelas/cóncavos; engrase de cams (cono)', 'system' => 'trituracion'],
                    ['interval_hours' => 40, 'task' => 'Tensión y estado de bandas; asientos de toggle (mandíbula)', 'system' => 'transmision'],
                    ['interval_hours' => 250, 'task' => 'Filtros de aceite/combustible de motor (plantas móviles LT/ST/NT)', 'system' => 'motor_diesel'],
                    ['interval_hours' => 1000, 'task' => 'Filtros y respiraderos de lubricación; bandas de transmisión', 'system' => 'lubricacion'],
                    ['interval_hours' => 1000, 'task' => 'Ajuste de setting y verificación de protección contra tramp iron', 'system' => 'trituracion'],
                    ['interval_hours' => 2000, 'task' => 'Cambio de aceite del sistema de lubricación (o muestreo con filtración offline)', 'system' => 'lubricacion'],
                    ['interval_hours' => 2000, 'task' => 'Cambio de muelas/corazas y revisión de excéntrica / countershaft', 'system' => 'trituracion'],
                ],
            ],
            [
                'manufacturer' => 'Metso',
                'equipment_class' => 'MOLINO',
                'name' => 'Mantenimiento de molienda 8/250/2000 h',
                'notes' => 'El cambio de blindaje se programa por desgaste medido, no solo por horas. Chumacera/trunnion es la falla más catastrófica: alarma típica >65 °C, trip >75 °C (calibrar por sitio).',
                'source_url' => 'https://www.metso.com/services/',
                'verified' => false,
                'items' => [
                    ['interval_hours' => 8, 'task' => 'Ronda de lubricación de chumaceras y piñón-corona', 'system' => 'lubricacion'],
                    ['interval_hours' => 8, 'task' => 'Verificación de presión y temperatura de aceite de muñones', 'system' => 'lubricacion'],
                    ['interval_hours' => 250, 'task' => 'Inspección de sello de descarga y trommel', 'system' => 'molienda'],
                    ['interval_hours' => 250, 'task' => 'Revisión de alineación y respaldo de piñón-corona (AGMA 1010)', 'system' => 'transmision'],
                    ['interval_hours' => 2000, 'task' => 'Inspección y cambio de blindaje interno', 'system' => 'molienda'],
                    ['interval_hours' => 2000, 'task' => 'Análisis de aceite y vibración del conjunto motriz', 'system' => 'transmision'],
                ],
            ],
        ];
    }

    /**
     * Escalera común de equipo diésel subterráneo (Sandvik confirmada; Epiroc por convención).
     *
     * @return list<array<string, mixed>>
     */
    private static function mobileDieselPlanItems(): array
    {
        return [
            ['interval_hours' => 250, 'task' => 'Cambio de aceite y filtros de motor', 'system' => 'motor_diesel'],
            ['interval_hours' => 250, 'task' => 'Inspección de fugas hidráulicas y mangueras', 'system' => 'hidraulico'],
            ['interval_hours' => 250, 'task' => 'Revisión de frenos de servicio y estacionamiento', 'system' => 'frenos'],
            ['interval_hours' => 250, 'task' => 'Limpieza de radiador/enfriador y revisión de nivel de refrigerante', 'system' => 'enfriamiento'],
            ['interval_hours' => 500, 'task' => 'Cambio de filtro hidráulico y muestreo de aceite (ISO 4406)', 'system' => 'hidraulico'],
            ['interval_hours' => 500, 'task' => 'Filtros de aire primario/secundario y respiradero de tanque hidráulico', 'system' => 'motor_diesel'],
            ['interval_hours' => 500, 'task' => 'Inspección de bujes y pines de articulación', 'system' => 'chasis'],
            ['interval_hours' => 1000, 'task' => 'Cambio de aceite de transmisión y convertidor', 'system' => 'transmision'],
            ['interval_hours' => 1000, 'task' => 'Cambio de aceite de diferenciales y mandos finales', 'system' => 'tren_motriz'],
            ['interval_hours' => 1000, 'task' => 'Análisis de aceite de motor, transmisión y ejes', 'system' => 'motor_diesel'],
            ['interval_hours' => 2000, 'task' => 'Cambio de aceite hidráulico completo y limpieza de tanque', 'system' => 'hidraulico'],
            ['interval_hours' => 2000, 'task' => 'Inspección mayor de tren motriz y ejes', 'system' => 'tren_motriz'],
            ['interval_hours' => 2000, 'task' => 'Revisión de turbo, inyección y prueba de emisiones', 'system' => 'motor_diesel'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function drillRigPlanItems(): array
    {
        return [
            ['interval_hours' => 250, 'task' => 'Inspección de martillo de percusión y acumuladores', 'system' => 'percusion'],
            ['interval_hours' => 250, 'task' => 'Revisión de viga de avance, cadena y guías', 'system' => 'viga_avance'],
            ['interval_hours' => 250, 'task' => 'Verificación de agua de barrenación y bomba', 'system' => 'agua_barrenacion'],
            ['interval_hours' => 500, 'task' => 'Cambio de aceite de rotación y revisión de husillo', 'system' => 'rotacion'],
            ['interval_hours' => 1000, 'task' => 'Reconstrucción o cambio de martillo de percusión', 'system' => 'percusion'],
            ['interval_hours' => 1000, 'task' => 'Inspección de boom, cilindros y sensores de posición', 'system' => 'boom'],
        ];
    }

    /**
     * Escribe el catálogo de forma idempotente. Respeta los registros marcados como verificados
     * por el usuario (no sobrescribe un `verified = true` local con una definición nueva).
     *
     * @return array{models: int, plans: int, items: int}
     */
    public static function sync(): array
    {
        $models = 0;
        foreach (self::models() as $definition) {
            $existing = OemEquipmentModel::query()
                ->where('manufacturer', $definition['manufacturer'])
                ->where('model', $definition['model'])
                ->first();

            // Un dato confirmado localmente contra el manual del cliente no se sobrescribe.
            if ($existing?->verified === true && ($definition['verified'] ?? false) !== true) {
                continue;
            }

            OemEquipmentModel::query()->updateOrCreate(
                ['manufacturer' => $definition['manufacturer'], 'model' => $definition['model']],
                $definition,
            );
            $models++;
        }

        $plans = 0;
        $items = 0;
        foreach (self::plans() as $definition) {
            $planItems = $definition['items'];
            unset($definition['items']);

            $existing = OemMaintenancePlan::query()
                ->where('manufacturer', $definition['manufacturer'])
                ->where('equipment_class', $definition['equipment_class'])
                ->where('name', $definition['name'])
                ->first();

            if ($existing?->verified === true && ($definition['verified'] ?? false) !== true) {
                continue;
            }

            $plan = OemMaintenancePlan::query()->updateOrCreate(
                [
                    'manufacturer' => $definition['manufacturer'],
                    'equipment_class' => $definition['equipment_class'],
                    'name' => $definition['name'],
                ],
                $definition,
            );
            $plans++;

            foreach ($planItems as $item) {
                OemMaintenancePlanItem::query()->updateOrCreate(
                    [
                        'oem_maintenance_plan_id' => $plan->id,
                        'interval_hours' => $item['interval_hours'],
                        'task' => $item['task'],
                    ],
                    $item + ['oem_maintenance_plan_id' => $plan->id],
                );
                $items++;
            }
        }

        return ['models' => $models, 'plans' => $plans, 'items' => $items];
    }

    /**
     * Asocia modelos OEM al catálogo de equipos de una empresa (Mein, Dom-G, etc.).
     *
     * 1) Enlaza ítems existentes por fabricante + modelo (nombre o specifications.modelo).
     * 2) Siembra un subconjunto representativo de modelos mineros como catalog_items
     *    cuando aún no existen, para que el predictivo y el alta de activos usen el mismo catálogo.
     *
     * @return array{linked: int, created: int}
     */
    public static function linkCompanyCatalog(int $companyId): array
    {
        $oemModels = OemEquipmentModel::query()->get();
        $linked = 0;
        $created = 0;

        $typeCache = [];
        foreach ($oemModels as $oem) {
            $class = (string) $oem->equipment_class;
            $typeCache[$class] ??= EquipmentType::withoutGlobalScope('company')->firstOrCreate(
                ['company_id' => $companyId, 'code' => \Illuminate\Support\Str::limit($class, 60, '')],
                ['name' => \Illuminate\Support\Str::title(str_replace('_', ' ', strtolower($class)))],
            );

            $matched = CatalogItem::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where(function ($q) use ($oem) {
                    $q->where('manufacturer', 'ilike', $oem->manufacturer)
                        ->where(function ($inner) use ($oem) {
                            $inner->where('name', 'ilike', '%'.$oem->model.'%')
                                ->orWhere('code', 'ilike', '%'.\Illuminate\Support\Str::slug($oem->model, '-').'%')
                                ->orWhere('specifications->modelo', 'ilike', $oem->model);
                        });
                })
                ->get();

            foreach ($matched as $item) {
                if ((int) $item->oem_equipment_model_id !== (int) $oem->id) {
                    $item->update(['oem_equipment_model_id' => $oem->id]);
                    $linked++;
                }
            }
        }

        // Subconjunto demo: un modelo representativo por clase/OEM principal.
        $seedKeys = [
            ['Epiroc', 'ST14 S'],
            ['Epiroc', 'MT42 S'],
            ['Epiroc', 'Boomer M2 D'],
            ['Sandvik', 'LH514'],
            ['Sandvik', 'TH551i'],
            ['Sandvik', 'DD422i'],
            ['Metso', 'HP300'],
            ['Metso', 'Ball Mill (genérico)'],
        ];

        foreach ($seedKeys as [$manufacturer, $model]) {
            $oem = $oemModels->first(
                fn (OemEquipmentModel $m) => $m->manufacturer === $manufacturer && $m->model === $model,
            );
            if ($oem === null) {
                continue;
            }

            $class = (string) $oem->equipment_class;
            $type = $typeCache[$class] ??= EquipmentType::withoutGlobalScope('company')->firstOrCreate(
                ['company_id' => $companyId, 'code' => \Illuminate\Support\Str::limit($class, 60, '')],
                ['name' => \Illuminate\Support\Str::title(str_replace('_', ' ', strtolower($class)))],
            );

            $code = \Illuminate\Support\Str::limit(
                \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($manufacturer.' '.$model, '-')),
                60,
                '',
            );

            $item = CatalogItem::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'equipment_type_id' => $type->id,
                    'name' => trim($manufacturer.' '.$model),
                    'manufacturer' => $manufacturer,
                    'oem_equipment_model_id' => $oem->id,
                    'specifications' => array_filter([
                        'modelo' => $model,
                        'equipment_class' => $class,
                        'family' => $oem->family,
                        'application' => $oem->application,
                    ]),
                ],
            );

            if ($item->wasRecentlyCreated) {
                $created++;
            } elseif ((int) $item->oem_equipment_model_id !== (int) $oem->id) {
                $item->update(['oem_equipment_model_id' => $oem->id]);
                $linked++;
            }
        }

        return ['linked' => $linked, 'created' => $created];
    }

    /**
     * Resuelve el id de modelo OEM por fabricante + modelo (tolerante a espacios/mayúsculas).
     */
    public static function resolveOemModelId(?string $manufacturer, ?string $model): ?int
    {
        if ($manufacturer === null || $model === null || trim($manufacturer) === '' || trim($model) === '') {
            return null;
        }

        $id = OemEquipmentModel::query()
            ->where('manufacturer', 'ilike', trim($manufacturer))
            ->where('model', 'ilike', trim($model))
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        // Coincidencia parcial del modelo (p. ej. "ST14" vs "ST14 S").
        $id = OemEquipmentModel::query()
            ->where('manufacturer', 'ilike', trim($manufacturer))
            ->where(function ($q) use ($model) {
                $needle = trim($model);
                $q->where('model', 'ilike', $needle.'%')
                    ->orWhere('model', 'ilike', '%'.$needle.'%');
            })
            ->orderByRaw('LENGTH(model) ASC')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
