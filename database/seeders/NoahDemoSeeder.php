<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Models\Asset;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\FormDefinition;
use App\Models\FormOptionCatalog;
use App\Models\FormVersion;
use App\Models\PromptTemplate;
use App\Models\ReportSectionTemplate;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplyItem;
use App\Models\User;
use App\Services\Workflow\WorkflowRuntime;
use App\Services\Identity\CompanyAuthorizationService;
use Database\Seeders\Support\DemoClientLogoGenerator;
use Database\Seeders\Support\DemoDesignDraftVersions;
use Illuminate\Database\Seeder;

class NoahDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('noah.demo_password');

        $company = Company::query()->updateOrCreate(
            ['name' => 'Demo Industrial'],
            [
                'legal_name' => 'Centro de Servicio Premium Noah S.A. de C.V.',
                'currency' => 'MXN',
                'is_active' => true,
                'form_max_image_size_kb' => 2048,
                'form_allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ]
        );

        $estadoCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'estado-componente-premium'],
            [
                'name' => 'Estado — revisión premium',
                'options' => [
                    [
                        'value' => 'operativo',
                        'label' => 'Operativo',
                        'description' => 'Componente dentro de especificación de fábrica; sin desgaste relevante.',
                    ],
                    [
                        'value' => 'revision',
                        'label' => 'Revisión',
                        'description' => 'Desgaste o lectura en límite; programar seguimiento en el próximo servicio.',
                    ],
                    [
                        'value' => 'no_aplica',
                        'label' => 'No aplica',
                        'description' => 'Sistema no instalado, no accesible o excluido del alcance contratado.',
                    ],
                    [
                        'value' => 'falla',
                        'label' => 'Falla',
                        'description' => 'Condición insegura o fuera de tolerancia; requiere corrección antes de entrega.',
                    ],
                ],
            ]
        );

        $combustibleCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'nivel-combustible'],
            [
                'name' => 'Nivel de combustible',
                'options' => [
                    ['value' => 'vacio', 'label' => 'Reserva / vacío', 'description' => 'Menos de 1/8 de tanque.'],
                    ['value' => 'cuarto', 'label' => '1/4', 'description' => 'Aproximadamente un cuarto de tanque.'],
                    ['value' => 'medio', 'label' => '1/2', 'description' => 'Medio tanque.'],
                    ['value' => 'tres_cuartos', 'label' => '3/4', 'description' => 'Tres cuartos de tanque.'],
                    ['value' => 'lleno', 'label' => 'Lleno', 'description' => 'Tanque al máximo indicado.'],
                ],
            ]
        );

        $siNoCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'si-no-servicio'],
            [
                'name' => 'Sí / No — servicio',
                'options' => [
                    ['value' => 'si', 'label' => 'Sí', 'description' => 'Se realizó o aplica en este servicio.'],
                    ['value' => 'no', 'label' => 'No', 'description' => 'No se realizó en este servicio.'],
                    ['value' => 'no_aplica', 'label' => 'No aplica', 'description' => 'No corresponde al alcance.'],
                ],
            ]
        );

        $componentField = static fn (string $key, string $label, bool $required = true) => [
            'key' => $key,
            'type' => 'options',
            'label' => $label,
            'required' => $required,
            'option_catalog_id' => $estadoCatalog->id,
        ];

        $recommendedPhoto = static fn (string $key, string $label, bool $multiple = false, int $maxImages = 4) => [
            'key' => $key,
            'type' => 'photo',
            'label' => $multiple
                ? $label.' (hasta '.$maxImages.' fotos)'
                : $label.' (1 foto)',
            'required' => false,
            'allow_multiple' => $multiple,
            'max_images' => $multiple ? $maxImages : 1,
            'caption_enabled' => true,
            'caption_required' => false,
        ];

        $demoClient = Client::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CLI-001'],
            [
                'legal_name' => 'Automotriz Ejecutiva S.A. de C.V.',
                'trade_name' => 'Cliente Premium Demo',
                'tax_id' => 'APE850101ABC',
                'billing_email' => 'facturacion@clientepremium.example',
                'is_active' => true,
            ]
        );
        $this->seedDemoClientLogo($demoClient);

        $site = Site::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Centro de servicio premium'],
            ['address' => 'Av. Reforma 2500, CDMX']
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@noah.local'],
            ['name' => 'Administrador Noah', 'password' => $password]
        );

        $technician = User::query()->updateOrCreate(
            ['email' => 'tecnico@noah.local'],
            ['name' => 'Técnico Demo', 'password' => $password]
        );

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'supervisor@noah.local'],
            ['name' => 'Supervisor Demo', 'password' => $password]
        );

        $billing = User::query()->updateOrCreate(
            ['email' => 'facturacion@noah.local'],
            ['name' => 'Facturación Demo', 'password' => $password]
        );

        foreach ([
            [$admin, MembershipRole::Administrator],
            [$technician, MembershipRole::Technician],
            [$supervisor, MembershipRole::Supervisor],
            [$billing, MembershipRole::Billing],
        ] as [$user, $role]) {
            CompanyMembership::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['role' => $role, 'is_active' => true]
            );
        }

        PromptTemplate::query()->updateOrCreate(
            ['company_id' => null, 'slug' => 'grammar_correction_v1', 'version' => 1],
            [
                'provider' => 'local',
                'system_prompt' => 'Eres un corrector de textos técnicos. No agregues información nueva.',
                'user_template' => '{{technician_text}}',
                'is_active' => true,
            ]
        );

        $catalog = CatalogItem::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'VEH-SUV-PREM'],
            ['name' => 'SUV premium AWD 3.0T', 'manufacturer' => 'Línea executive']
        );

        $asset = Asset::query()->updateOrCreate(
            ['company_id' => $company->id, 'tag' => 'VEH-4582-MX'],
            [
                'site_id' => $site->id,
                'catalog_item_id' => $catalog->id,
                'serial_number' => 'WBA8E9G50JNU45821',
                'location_label' => 'Bahía 3 — recepción',
            ]
        );

        $supplier = Supplier::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'PROV-001'],
            [
                'name' => 'Refacciones del Norte',
                'contact_email' => 'ventas@refnorte.example',
            ]
        );

        SupplyItem::query()->updateOrCreate(
            ['company_id' => $company->id, 'sku' => 'FIL-ACE-PREM'],
            [
                'name' => 'Filtro de aceite OEM premium',
                'unit' => 'pza',
                'standard_cost' => 890.00,
                'supplier_id' => $supplier->id,
            ]
        );

        $formDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'revision-mayor-vehiculo-premium'],
            ['name' => 'Revisión mayor vehículo — agencia premium']
        );

        $formVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $formDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => [
                    'sections' => [
                        [
                            'title' => 'Kilometraje y recepción',
                            'fields' => [
                                [
                                    'key' => 'kilometraje',
                                    'type' => 'number',
                                    'label' => 'Kilometraje actual (km)',
                                    'required' => true,
                                ],
                                [
                                    'key' => 'nivel_combustible',
                                    'type' => 'select',
                                    'label' => 'Nivel de combustible al ingreso',
                                    'required' => false,
                                    'option_catalog_id' => $combustibleCatalog->id,
                                ],
                                [
                                    'key' => 'observaciones_recepcion',
                                    'type' => 'textarea',
                                    'label' => 'Daños preexistentes / solicitudes del cliente',
                                    'required' => false,
                                ],
                                [
                                    'key' => 'foto_tablero',
                                    'type' => 'photo',
                                    'label' => 'Evidencia: tablero (km y testigos)',
                                    'required' => false,
                                    'allow_multiple' => false,
                                    'caption_enabled' => true,
                                    'caption_required' => false,
                                ],
                            ],
                        ],
                        [
                            'title' => 'Frenos',
                            'fields' => [
                                $componentField('frenos', 'Estado general — discos, pastillas y líquido DOT'),
                                [
                                    'key' => 'frenos_espesor_pastillas_mm',
                                    'type' => 'number',
                                    'label' => 'Espesor pastillas delanteras (mm) — medición taller',
                                    'required' => false,
                                ],
                                [
                                    'key' => 'frenos_notas',
                                    'type' => 'textarea',
                                    'label' => 'Notas de frenos',
                                    'required' => false,
                                ],
                                $recommendedPhoto('foto_frenos', 'Discos de freno / pastillas (evidencia)', true, 4),
                            ],
                        ],
                        [
                            'title' => 'Filtros',
                            'fields' => [
                                $componentField('filtros', 'Estado — aceite, aire motor y habitáculo'),
                                [
                                    'key' => 'filtros_cambio_aceite',
                                    'type' => 'select',
                                    'label' => '¿Se reemplazó filtro de aceite en este servicio?',
                                    'required' => false,
                                    'option_catalog_id' => $siNoCatalog->id,
                                ],
                                $recommendedPhoto('foto_filtro_aceite', 'Filtro de aceite (nuevo o retirado)'),
                            ],
                        ],
                        [
                            'title' => 'Aceite y fluidos',
                            'fields' => [
                                $componentField('aceite', 'Aceite motor, nivel y fugas'),
                                [
                                    'key' => 'aceite_viscosidad',
                                    'type' => 'text',
                                    'label' => 'Viscosidad / especificación aplicada (ej. 0W-20 OEM)',
                                    'required' => false,
                                ],
                                $recommendedPhoto('foto_varilla_aceite', 'Varilla o etiqueta de servicio'),
                            ],
                        ],
                        [
                            'title' => 'Batería',
                            'fields' => [
                                $componentField('bateria', 'Batería, bornes y alternador (prueba de carga)'),
                                [
                                    'key' => 'bateria_cca',
                                    'type' => 'number',
                                    'label' => 'CCA medido (A) — prueba conductancia',
                                    'required' => false,
                                ],
                                $recommendedPhoto('foto_bateria', 'Batería y estado de bornes'),
                            ],
                        ],
                        [
                            'title' => 'Luces',
                            'fields' => [
                                $componentField('luces', 'Faros, direccionales, stop, reversa y interior'),
                                [
                                    'key' => 'luces_alineacion',
                                    'type' => 'options',
                                    'label' => 'Alineación / altura de faros (si aplica)',
                                    'required' => false,
                                    'option_catalog_id' => $estadoCatalog->id,
                                ],
                                $recommendedPhoto('foto_luces', 'Faros encendidos (baja y alta)'),
                            ],
                        ],
                        [
                            'title' => 'Fusibles y electricidad',
                            'fields' => [
                                $componentField('fusibles', 'Caja de fusibles / relés — sin sobrecalentamiento'),
                                [
                                    'key' => 'fusibles_notas',
                                    'type' => 'textarea',
                                    'label' => 'Circuitos revisados o fusibles reemplazados',
                                    'required' => false,
                                ],
                                $recommendedPhoto('foto_caja_fusibles', 'Caja de fusibles abierta'),
                            ],
                        ],
                        [
                            'title' => 'Revisiones Plus (opcional — paquete premium)',
                            'fields' => [
                                $componentField('plus_suspension', 'Suspensión y amortiguadores', false),
                                $componentField('plus_transmision', 'Transmisión / transfer (AWD)', false),
                                $componentField('plus_neumaticos', 'Neumáticos (profundidad y presión)', false),
                                $componentField('plus_aire_acondicionado', 'Climatización y filtro cabina', false),
                                $componentField('plus_diagnostico_obd', 'Escaneo OBD-II (códigos activos)', false),
                                $recommendedPhoto('foto_neumaticos', 'Neumáticos — desgaste', true, 4),
                            ],
                        ],
                        [
                            'title' => 'Dictamen y entrega',
                            'fields' => [
                                [
                                    'key' => 'observaciones_taller',
                                    'type' => 'textarea',
                                    'label' => 'Dictamen técnico del asesor de servicio',
                                    'required' => false,
                                ],
                                [
                                    'key' => 'foto_exterior',
                                    'type' => 'photo',
                                    'label' => 'Evidencia: vehículo en bahía (entrega)',
                                    'required' => false,
                                    'allow_multiple' => false,
                                    'caption_enabled' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        DemoDesignDraftVersions::ensureFormDraft($formDef, $formVersion, $admin);

        $sectionAlcance = ReportSectionTemplate::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'alcance-servicio-premium'],
            [
                'name' => 'Alcance del servicio premium',
                'description' => 'Texto estándar de recepción en agencia',
                'body' => '<p>Revisión mayor bajo estándar de agencia premium: registro de <strong>kilometraje</strong>, inspección de <strong>frenos</strong>, <strong>filtros</strong>, <strong>aceite</strong>, <strong>batería</strong>, <strong>luces</strong> y <strong>fusibles</strong>, con evidencia fotográfica donde aplica y bloque opcional <em>Revisiones Plus</em>.</p><p>Incluye prueba de arranque, verificación de testigos en tablero, lavado y aspirado de cortesía.</p>',
            ]
        );

        $sectionGarantia = ReportSectionTemplate::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'garantia-recomendaciones'],
            [
                'name' => 'Garantía y recomendaciones',
                'description' => 'Cierre legal y próximos servicios',
                'body' => '<p><strong>Garantía de mano de obra:</strong> 12 meses o 20 000 km (lo que ocurra primero) sobre trabajos declarados en este informe.</p><p><strong>Recomendación:</strong> próximo servicio de aceite y filtro según manual del fabricante o en 10 000 km.</p><p>El cliente recibe vehículo lavado y aspirado como parte del paquete premium.</p>',
            ]
        );

        $reportTpl = ReportTemplate::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'informe-revision-mayor-vehiculo'],
            [
                'name' => 'Informe revisión mayor vehículo',
                'description' => 'Informe alineado al formulario demo revisión mayor SUV premium',
            ]
        );

        $reportVersion = ReportTemplateVersion::query()->updateOrCreate(
            ['report_template_id' => $reportTpl->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'components' => [
                    ['type' => 'title', 'text' => 'Informe de revisión mayor premium', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => '{{company}} · {{asset_tag}}', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Kilometraje y recepción', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'kilometraje', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'nivel_combustible', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'observaciones_recepcion', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_tablero', 'align' => 'center'],
                    ['type' => 'divider', 'style' => 'solid', 'margin_pt' => 10],
                    ['type' => 'subtitle', 'text' => 'Frenos', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'frenos', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'frenos_espesor_pastillas_mm', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'frenos_notas', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_frenos', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Filtros', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'filtros', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'filtros_cambio_aceite', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_filtro_aceite', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Aceite y fluidos', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'aceite', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'aceite_viscosidad', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_varilla_aceite', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Batería', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'bateria', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'bateria_cca', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_bateria', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Luces', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'luces', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'luces_alineacion', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_luces', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Fusibles y electricidad', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'fusibles', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'fusibles_notas', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_caja_fusibles', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Revisiones Plus (opcional)', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'plus_suspension', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'plus_transmision', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'plus_neumaticos', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'plus_aire_acondicionado', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'plus_diagnostico_obd', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_neumaticos', 'align' => 'center'],
                    ['type' => 'divider', 'style' => 'solid', 'margin_pt' => 10],
                    ['type' => 'subtitle', 'text' => 'Dictamen y entrega', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'observaciones_taller', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_exterior', 'align' => 'center'],
                    ['type' => 'divider', 'style' => 'solid', 'margin_pt' => 12],
                    ['type' => 'subtitle', 'text' => 'Anexos', 'align' => 'left'],
                    ['type' => 'section_template', 'section_template_id' => $sectionAlcance->id, 'align' => 'left'],
                    ['type' => 'section_template', 'section_template_id' => $sectionGarantia->id, 'align' => 'left'],
                ],
                'page_settings' => [
                    'size' => 'A4',
                    'font_family' => 'source_sans',
                    'header' => [
                        'enabled' => true,
                        'text' => '{{company}} · Revisión mayor · Rutina #{{routine_id}}',
                    ],
                    'footer' => [
                        'enabled' => true,
                        'text' => 'Documento confidencial — generado por **Noah**',
                    ],
                    'page_number' => ['enabled' => true, 'start_at' => 2],
                    'cover_page' => [
                        'enabled' => true,
                        'title' => 'Revisión mayor premium',
                        'subtitle' => '{{company}} · {{asset_tag}}',
                        'body' => 'Inspección mayor: frenos, filtros, aceite, batería, luces, fusibles y kilometraje. Evidencias y Plus opcional.',
                        'show_date' => true,
                        'omit_header_footer' => true,
                        'use_client_logo' => true,
                        'client_id' => $demoClient->id,
                    ],
                    'typography' => ['title_pt' => 22, 'subtitle_pt' => 14, 'body_pt' => 11],
                ],
            ]
        );

        DemoDesignDraftVersions::ensureReportDraft($reportTpl, $reportVersion, $admin);

        $routineType = RoutineType::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'revision-mayor-vehiculo-premium'],
            [
                'name' => 'Revisión mayor vehículo (premium)',
                'form_version_id' => $formVersion->id,
                'report_template_version_id' => $reportVersion->id,
                'is_active' => true,
            ]
        );

        $workflowDef = app(WorkflowRuntime::class)->seedDefinitionForCompany($company->id);
        $routineType->update(['workflow_definition_id' => $workflowDef->id]);

        if (! Routine::query()->where('company_id', $company->id)->exists()) {
            Routine::query()->create([
                'company_id' => $company->id,
                'site_id' => $site->id,
                'asset_id' => $asset->id,
                'routine_type_id' => $routineType->id,
                'assigned_to' => $technician->id,
                'status' => RoutineStatus::Assigned,
                'scheduled_at' => now()->addDay(),
            ]);
        }

        $workflow = app(WorkflowRuntime::class);
        Routine::query()
            ->where('company_id', $company->id)
            ->whereDoesntHave('workflowInstance')
            ->each(fn (Routine $routine) => $workflow->ensureInstance($routine->load('routineType.workflowDefinition')));

        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();

        DemoClientLogoGenerator::writeAssetFile();
    }

    private function seedDemoClientLogo(Client $client): void
    {
        $relativePath = DemoClientLogoGenerator::syncForClient($client->id);
        if ($relativePath !== '') {
            $client->update(['logo_path' => $relativePath]);
        }
    }
}
