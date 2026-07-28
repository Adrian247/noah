<?php

namespace Database\Seeders;

use App\Enums\FormUsage;
use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Models\Asset;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EquipmentType;
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
use App\Models\SupplyType;
use App\Models\User;
use App\Services\Workflow\WorkflowRuntime;
use App\Services\Identity\CompanyAuthorizationService;
use Database\Seeders\Support\DemoClientLogoGenerator;
use Database\Seeders\Support\DemoDesignDraftVersions;
use Database\Seeders\Support\NormalizedVehicleFormSchema;
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

        $clientUser = User::query()->updateOrCreate(
            ['email' => 'cliente@noah.local'],
            ['name' => 'Cliente Portal Demo', 'password' => $password]
        );

        foreach ([
            [$admin, MembershipRole::Administrator, null],
            [$technician, MembershipRole::Technician, null],
            [$supervisor, MembershipRole::Supervisor, null],
            [$billing, MembershipRole::Billing, null],
            [$clientUser, MembershipRole::Client, $demoClient->id],
        ] as [$user, $role, $clientId]) {
            CompanyMembership::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['role' => $role, 'is_active' => true, 'client_id' => $clientId]
            );
        }

        $normalizedFormDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'inspeccion-vehiculo-v1'],
            ['name' => 'Inspección vehículo (normalizada)', 'usage' => FormUsage::Equipment]
        );

        $normalizedFormVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $normalizedFormDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => [
                    'sections' => NormalizedVehicleFormSchema::sections(
                        $estadoCatalog->id,
                        $combustibleCatalog->id,
                        $siNoCatalog->id,
                    ),
                ],
            ]
        );

        DemoDesignDraftVersions::ensureFormDraft($normalizedFormDef, $normalizedFormVersion, $admin);

        $typeVehiculo = EquipmentType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'vehiculo'],
            [
                'name' => 'Vehículo',
                'description' => 'Automóviles y camionetas',
                'sort_order' => 1,
                'default_form_definition_id' => $normalizedFormDef->id,
            ]
        );

        EquipmentType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'motor'],
            ['name' => 'Motores', 'sort_order' => 2]
        );

        EquipmentType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'bomba'],
            ['name' => 'Bombas', 'sort_order' => 3]
        );

        $supplyTypeFiltros = SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'filtros'],
            ['name' => 'Filtros', 'sort_order' => 1]
        );
        $supplyTypeFrenos = SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'frenos'],
            ['name' => 'Frenos y balatas', 'sort_order' => 2]
        );
        $supplyTypeSuspension = SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'suspension'],
            ['name' => 'Suspensión', 'sort_order' => 3]
        );
        SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'fluidos'],
            ['name' => 'Fluidos y lubricantes', 'sort_order' => 4]
        );

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
            ['company_id' => $company->id, 'code' => 'VEH-L200-2018'],
            [
                'equipment_type_id' => $typeVehiculo->id,
                'name' => 'Mitsubishi L200 2018',
                'manufacturer' => 'Mitsubishi',
                'specifications' => [
                    'modelo' => 'L200',
                    'anio' => 2018,
                    'mercado' => 'MX',
                    'chasis' => 'Rise Body',
                    'variante_demo' => '2.5L DI-D 4x4',
                    'motor' => '2.5L Turbo Diésel Common Rail',
                    'potencia_hp' => 134,
                    'torque_lb_pie' => 232,
                    'transmision' => 'Manual 5 vel. + reductora',
                    'traccion' => '4x4 Easy Select',
                    'frenos_delanteros' => 'Discos ventilados',
                    'frenos_traseros' => 'Tambor',
                    'tanque_litros' => 75,
                    'capacidad_carga_kg' => 1050,
                    'dimensiones_mm' => [
                        'largo' => 5205,
                        'ancho' => 1785,
                        'alto' => 1775,
                        'batalla' => 3000,
                    ],
                ],
            ]
        );

        $asset = Asset::query()->updateOrCreate(
            ['company_id' => $company->id, 'tag' => 'L200-2018-DEMO'],
            [
                'site_id' => $site->id,
                'catalog_item_id' => $catalog->id,
                'serial_number' => 'MMBJNKB40JH000001',
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

        SupplyItem::query()
            ->where('company_id', $company->id)
            ->where('sku', 'FIL-ACE-PREM')
            ->delete();

        foreach ([
            [
                'sku' => 'FIL-1230A153-OEM',
                'supply_type_id' => $supplyTypeFiltros->id,
                'name' => 'Filtro de aceite Mitsubishi OEM',
                'unit' => 'pza',
                'standard_cost' => 550.00,
                'specifications' => [
                    'marca' => 'Mitsubishi',
                    'referencia_oem' => '1230A153',
                ],
            ],
            [
                'sku' => 'FIL-AIR-2030515-SAK',
                'supply_type_id' => $supplyTypeFiltros->id,
                'name' => 'Filtro de aire Sakura 2030515',
                'unit' => 'pza',
                'standard_cost' => 341.00,
                'specifications' => [
                    'marca' => 'Sakura',
                    'referencia_oem' => '2030515',
                ],
            ],
            [
                'sku' => 'FRE-P54038-BRM',
                'supply_type_id' => $supplyTypeFrenos->id,
                'name' => 'Balatas delanteras Brembo P54038 (juego)',
                'unit' => 'jgo',
                'standard_cost' => 1268.00,
                'specifications' => [
                    'marca' => 'Brembo',
                    'referencia_oem' => 'P54038',
                ],
            ],
            [
                'sku' => 'SUS-AMORT-GROB-PAR',
                'supply_type_id' => $supplyTypeSuspension->id,
                'name' => 'Amortiguadores delanteros GROB (par)',
                'unit' => 'par',
                'standard_cost' => 1698.00,
                'specifications' => [
                    'marca' => 'GROB',
                    'referencia_oem' => 'AMORT-DEL-PAR',
                ],
            ],
        ] as $supplySeed) {
            SupplyItem::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $supplySeed['sku']],
                [
                    'supply_type_id' => $supplySeed['supply_type_id'],
                    'name' => $supplySeed['name'],
                    'unit' => $supplySeed['unit'],
                    'standard_cost' => $supplySeed['standard_cost'],
                    'specifications' => $supplySeed['specifications'],
                    'supplier_id' => $supplier->id,
                ]
            );
        }

        $formDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'revision-mayor-vehiculo-premium'],
            ['name' => 'Revisión mayor vehículo — agencia premium', 'usage' => FormUsage::Routine]
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
                                    'type' => 'number',
                                    'label' => 'Grado SAE numérico (ej. 20 para 0W-20)',
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
                    ['type' => 'title', 'text' => 'Informe de inspección vehicular', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => '{{company}} · {{asset_tag}}', 'align' => 'center'],
                    ['type' => 'subtitle', 'text' => 'Recepción', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'kilometraje', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'nivel_combustible', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'observaciones_recepcion', 'align' => 'left'],
                    ['type' => 'divider', 'style' => 'solid', 'margin_pt' => 10],
                    ['type' => 'subtitle', 'text' => 'Motor y fluidos', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'motor_estado', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'aceite_motor', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'filtro_aceite_reemplazado', 'align' => 'left'],
                    ['type' => 'subtitle', 'text' => 'Frenos', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'frenos_delanteros', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'frenos_traseros', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'liquido_frenos', 'align' => 'left'],
                    ['type' => 'subtitle', 'text' => 'Filtros', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'filtro_aire', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'filtro_habitaculo', 'align' => 'left'],
                    ['type' => 'subtitle', 'text' => 'Suspensión y eléctrico', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'suspension', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'direccion', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'bateria', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'luces', 'align' => 'left'],
                    ['type' => 'subtitle', 'text' => 'Cierre', 'align' => 'left'],
                    ['type' => 'paragraph', 'field' => 'comentarios_cierre', 'align' => 'left'],
                    ['type' => 'image', 'field' => 'foto_evidencia', 'align' => 'center'],
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
                        'body' => 'Inspección vehicular normalizada: motor, frenos, filtros, suspensión y eléctrico básico.',
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

        \App\Models\AssetClientAssignment::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'asset_id' => $asset->id,
                'client_id' => $demoClient->id,
                'unassigned_at' => null,
            ],
            [
                'serial_number' => $asset->serial_number ?? 'MMBJNKB40JH000001',
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ]
        );

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
