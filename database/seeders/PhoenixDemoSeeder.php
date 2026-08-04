<?php

namespace Database\Seeders;

use App\Enums\FormUsage;
use App\Enums\MembershipRole;
use App\Enums\ServiceCategory;
use App\Models\Asset;
use App\Models\AssetClientAssignment;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EquipmentType;
use App\Models\FormDefinition;
use App\Models\FormOptionCatalog;
use App\Models\FormVersion;
use App\Models\PredictiveAlgorithmVersion;
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
use App\Services\Identity\CompanyAuthorizationService;
use App\Services\Reports\ReportPresetApplier;
use App\Services\Routines\DemoRoutineFactory;
use App\Services\Workflow\WorkflowRuntime;
use App\Support\DemoAccounts;
use App\Support\PlatformAdmin;
use App\Support\PlatformCatalogCompany;
use App\Support\Predictive\FailureModeCatalog;
use App\Support\Ai\OperationalAssistantPrompt;
use App\Support\Predictive\OemCatalog;
use App\Support\SupplyUnits;
use Database\Seeders\Support\DemoClientLogoGenerator;
use Database\Seeders\Support\DemoDesignDraftVersions;
use Database\Seeders\Support\NormalizedSupplyFormSchemas;
use Database\Seeders\Support\NormalizedVehicleFormSchema;
use Database\Seeders\Support\TenantDemoProfile;
use Database\Seeders\Support\VehicleRegistrationFormSchema;
use Illuminate\Database\Seeder;

class PhoenixDemoSeeder extends Seeder
{
    /**
     * Playground Sandbox para la suite: en Docker APP_ENV suele ser `local` aunque corra PHPUnit,
     * así que no usamos environment('testing'). Detectamos el runner de PHPUnit directamente.
     */
    public static function shouldSeedSandboxPlayground(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL')
            || defined('__PHPUNIT_PHAR__')
            || (bool) env('PHOENIX_SEED_SANDBOX_PLAYGROUND', false);
    }

    public function run(): void
    {
        $tenantPassword = DemoAccounts::tenantPassword();
        $rootPassword = DemoAccounts::rootPassword();

        $platformAdmin = User::query()->updateOrCreate(
            ['email' => DemoAccounts::ROOT_EMAIL],
            [
                'name' => 'Administrador de sistema',
                'password' => $rootPassword,
                'is_platform_admin' => true,
            ],
        );
        PlatformAdmin::syncFlagFromConfig($platformAdmin);

        $companies = [];
        foreach ([TenantDemoProfile::mein(), TenantDemoProfile::domG()] as $profile) {
            $companies[] = $this->seedVirginTenant($profile, $tenantPassword);
        }
        // Sandbox: virgen en runtime normal (solo usuarios + cliente). Bajo PHPUnit se siembra
        // el playground operativo para DemoRoutineFactory y pruebas de flujo.
        $sandboxProfile = TenantDemoProfile::sandbox();
        $companies[] = self::shouldSeedSandboxPlayground()
            ? $this->seedDemonstrationTenant($sandboxProfile, $tenantPassword)
            : $this->seedVirginTenant($sandboxProfile, $tenantPassword);

        // OEM global primero: Artículos de sistema reutiliza Epiroc/Sandvik verificados.
        OemCatalog::sync();
        $this->seedPlatformCatalogCompany();

        // Baselines predicativos globales. El enlace a tenant solo aplica al playground (testing).
        $algorithm = PredictiveAlgorithmVersion::query()->updateOrCreate(
            ['semver' => '1.0.0'],
            [
                'status' => PredictiveAlgorithmVersion::STATUS_PUBLISHED,
                'kind' => \App\Enums\PredictiveAlgorithmKind::Maintenance->value,
                'notes' => 'Versión inicial publicada: predicción de servicios de mantenimiento (hazard-v2).',
                'metrics' => ['baseline_kind' => \App\Enums\PredictiveAlgorithmKind::Maintenance->value],
                'calibration' => [
                    'global_hazard_multiplier' => 1.0,
                    'driver_weights' => [
                        'intensidad_servicios' => 1.15,
                        'backlog_servicios' => 1.1,
                    ],
                ],
                'training_summary' => [
                    'note' => 'Semilla demo. Reentrena desde Plataforma → Algoritmo predictivo.',
                ],
                'created_by' => $platformAdmin->id,
                'published_by' => $platformAdmin->id,
                'published_at' => now(),
            ],
        );
        PredictiveAlgorithmVersion::query()->updateOrCreate(
            ['semver' => '1.0.0-mfg'],
            [
                'status' => PredictiveAlgorithmVersion::STATUS_PUBLISHED,
                'kind' => \App\Enums\PredictiveAlgorithmKind::Manufacturing->value,
                'notes' => 'Baseline manufactura (demand-v1).',
                'metrics' => ['baseline_kind' => \App\Enums\PredictiveAlgorithmKind::Manufacturing->value],
                'calibration' => ['global_rate_multiplier' => 1.0, 'pair_boosts' => []],
                'training_summary' => ['note' => 'Semilla demo manufactura.'],
                'created_by' => $platformAdmin->id,
                'published_by' => $platformAdmin->id,
                'published_at' => now(),
            ],
        );
        PredictiveAlgorithmVersion::query()->updateOrCreate(
            ['semver' => '1.0.0-inv'],
            [
                'status' => PredictiveAlgorithmVersion::STATUS_PUBLISHED,
                'kind' => \App\Enums\PredictiveAlgorithmKind::Inventory->value,
                'notes' => 'Baseline inventario / demanda de artículos (demand-v1).',
                'metrics' => ['baseline_kind' => \App\Enums\PredictiveAlgorithmKind::Inventory->value],
                'calibration' => ['global_rate_multiplier' => 1.0, 'pair_boosts' => []],
                'training_summary' => ['note' => 'Semilla demo inventario.'],
                'created_by' => $platformAdmin->id,
                'published_by' => $platformAdmin->id,
                'published_at' => now(),
            ],
        );
        foreach ($companies as $company) {
            if ($company->name !== TenantDemoProfile::sandbox()->companyName) {
                continue;
            }
            // Solo el playground de pruebas automatizadas enlaza predictivo / OEM al tenant.
            if (! self::shouldSeedSandboxPlayground()) {
                continue;
            }

            FailureModeCatalog::syncForCompany((int) $company->id);
            OemCatalog::linkCompanyCatalog((int) $company->id);
            $company->update([
                'allow_predictive_training_collection' => true,
                'predictive_algorithm_version_id' => $algorithm->id,
            ]);
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

        PromptTemplate::query()->updateOrCreate(
            ['company_id' => null, 'slug' => 'insights_assistant_v1', 'version' => 1],
            [
                'provider' => 'openai',
                'system_prompt' => OperationalAssistantPrompt::default(),
                'user_template' => '{{question}}',
                'is_active' => true,
            ]
        );

        PromptTemplate::query()->updateOrCreate(
            ['company_id' => null, 'slug' => 'vision_ocr_v1', 'version' => 1],
            [
                'provider' => 'openai',
                'system_prompt' => 'Extrae texto visible de placa o etiqueta. Solo el texto, sin explicación.',
                'user_template' => '{{image}}',
                'is_active' => true,
            ]
        );

        app(CompanyAuthorizationService::class)->bootstrapAllCompanies();

        $workflowRuntime = app(WorkflowRuntime::class);
        foreach ($companies as $company) {
            $workflowRuntime->syncStandardWorkflowForCompany((int) $company->id);
        }

        DemoClientLogoGenerator::writeAssetFile();
    }

    /**
     * Tenant virgen: empresa, roles, usuarios/contraseñas y cliente interno.
     * Sin catálogos, sitios, artículos, formularios ni rutinas demo.
     */
    private function seedVirginTenant(TenantDemoProfile $profile, string $password): Company
    {
        $company = Company::query()->updateOrCreate(
            ['name' => $profile->companyName],
            [
                'legal_name' => $profile->companyLegalName,
                'currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
                'form_max_image_size_kb' => 2048,
                'form_allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                'allow_predictive_training_collection' => false,
                'predictive_algorithm_version_id' => null,
                'fiscal_provider' => config('phoenix.billing.fiscal.default_provider', 'sandbox'),
            ]
        );

        $this->purgeTenantOperationalData($company, keepClientCodes: [$profile->clientCode]);

        app(CompanyAuthorizationService::class)->ensureCompanyRoles($company);

        $staffUserIds = [];
        foreach ($profile->staff as $staffRow) {
            $user = User::query()->updateOrCreate(
                ['email' => $staffRow['email']],
                ['name' => $staffRow['name'], 'password' => $password],
            );
            $staffUserIds[] = $user->id;

            CompanyMembership::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                [
                    'role' => $staffRow['role'],
                    'is_active' => true,
                    'client_id' => null,
                ],
            );
        }

        // Quitar membresías huérfanas (cuentas viejas de demos anteriores).
        CompanyMembership::query()
            ->where('company_id', $company->id)
            ->whereNotIn('user_id', $staffUserIds)
            ->delete();

        app(WorkflowRuntime::class)->seedDefinitionForCompany($company->id);

        $internalClient = $this->seedInternalClient($company, $profile);

        foreach ($profile->staff as $staffRow) {
            if (empty($staffRow['portal_client'])) {
                continue;
            }
            $user = User::query()->where('email', $staffRow['email'])->first();
            if ($user === null) {
                continue;
            }
            CompanyMembership::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->update(['client_id' => $internalClient->id]);
        }

        return $company;
    }

    /**
     * Elimina datos operativos de un tenant (rutinas, catálogos, predictivo, integraciones, etc.),
     * conservando empresa, roles Spatie, usuarios/membresías y clientes en $keepClientCodes.
     * El workflow estándar se vuelve a sembrar después del purge.
     *
     * @param  list<string>  $keepClientCodes
     */
    private function purgeTenantOperationalData(Company $company, array $keepClientCodes): void
    {
        $companyId = (int) $company->id;
        $db = \Illuminate\Support\Facades\DB::connection();
        $schema = $db->getSchemaBuilder();

        // Predictivo / confiabilidad (orden por FKs).
        foreach ([
            'failure_predictions',
            'equipment_component_replacements',
            'equipment_measurements',
            'equipment_failures',
            'equipment_events',
            'equipment_shift_logs',
            'equipment_work_orders',
            'failure_modes',
        ] as $table) {
            if ($schema->hasTable($table)) {
                $db->table($table)->where('company_id', $companyId)->delete();
            }
        }

        if ($schema->hasTable('invoice_evidences')) {
            $db->table('invoice_evidences')->where('company_id', $companyId)->delete();
        }
        $invoiceIds = $db->table('invoices')->where('company_id', $companyId)->pluck('id');
        if ($invoiceIds->isNotEmpty()) {
            $db->table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->delete();
        }
        $db->table('invoices')->where('company_id', $companyId)->delete();
        $db->table('generated_reports')->where('company_id', $companyId)->delete();
        $db->table('inventory_movements')->where('company_id', $companyId)->delete();

        $routineIds = $db->table('routines')->where('company_id', $companyId)->pluck('id');
        if ($routineIds->isNotEmpty()) {
            $executionIds = $db->table('routine_executions')->whereIn('routine_id', $routineIds)->pluck('id');
            if ($executionIds->isNotEmpty()) {
                if ($schema->hasTable('execution_evidences')) {
                    $db->table('execution_evidences')->whereIn('routine_execution_id', $executionIds)->delete();
                }
                $db->table('routine_consumptions')->whereIn('routine_execution_id', $executionIds)->delete();
            }
            $instanceIds = $db->table('workflow_instances')->whereIn('routine_id', $routineIds)->pluck('id');
            if ($instanceIds->isNotEmpty() && $schema->hasTable('workflow_transitions')) {
                $db->table('workflow_transitions')->whereIn('workflow_instance_id', $instanceIds)->delete();
            }
            $db->table('routine_executions')->whereIn('routine_id', $routineIds)->delete();
            $db->table('workflow_instances')->whereIn('routine_id', $routineIds)->delete();
        }
        $db->table('routines')->where('company_id', $companyId)->delete();
        $db->table('asset_client_assignments')->where('company_id', $companyId)->delete();
        $db->table('assets')->where('company_id', $companyId)->delete();
        $db->table('sites')->where('company_id', $companyId)->delete();
        $db->table('supply_items')->where('company_id', $companyId)->delete();
        $db->table('supply_types')->where('company_id', $companyId)->delete();
        $db->table('catalog_items')->where('company_id', $companyId)->delete();
        $db->table('equipment_types')->where('company_id', $companyId)->delete();
        $db->table('suppliers')->where('company_id', $companyId)->delete();
        $db->table('routine_types')->where('company_id', $companyId)->delete();

        $reportIds = $db->table('report_templates')->where('company_id', $companyId)->pluck('id');
        if ($reportIds->isNotEmpty()) {
            $db->table('report_template_versions')->whereIn('report_template_id', $reportIds)->delete();
        }
        $db->table('report_templates')->where('company_id', $companyId)->delete();
        if ($schema->hasTable('report_section_templates')) {
            $db->table('report_section_templates')->where('company_id', $companyId)->delete();
        }

        $formIds = $db->table('form_definitions')->where('company_id', $companyId)->pluck('id');
        if ($formIds->isNotEmpty()) {
            $db->table('form_versions')->whereIn('form_definition_id', $formIds)->delete();
        }
        $db->table('form_definitions')->where('company_id', $companyId)->delete();
        $db->table('form_option_catalogs')->where('company_id', $companyId)->delete();

        // Definiciones de workflow: se regeneran tras el purge.
        if ($schema->hasTable('workflow_definitions')) {
            $db->table('workflow_definitions')->where('company_id', $companyId)->delete();
        }

        foreach ([
            'automation_rules',
            'webhook_subscriptions',
            'dashboard_preferences',
            'device_push_tokens',
            'sync_events',
            'ai_invocations',
            'audit_entries',
            'catalog_import_logs',
        ] as $table) {
            if ($schema->hasTable($table) && $schema->hasColumn($table, 'company_id')) {
                $db->table($table)->where('company_id', $companyId)->delete();
            }
        }

        $clientsQuery = $db->table('clients')->where('company_id', $companyId);
        if ($keepClientCodes !== []) {
            $clientsQuery->whereNotIn('code', $keepClientCodes);
        }
        $removeClientIds = $clientsQuery->pluck('id');
        if ($removeClientIds->isNotEmpty()) {
            $db->table('company_memberships')
                ->where('company_id', $companyId)
                ->whereIn('client_id', $removeClientIds)
                ->update(['client_id' => null]);
            $db->table('clients')->whereIn('id', $removeClientIds)->delete();
        }
    }

    private function seedInternalClient(Company $company, TenantDemoProfile $profile): Client
    {
        return Client::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $profile->clientCode],
            [
                'legal_name' => $profile->clientLegalName,
                'trade_name' => $profile->clientTradeName,
                'tax_id' => null,
                'billing_email' => null,
                'is_active' => true,
            ]
        );
    }

    /** @deprecated use seedVirginTenant */
    private function seedVirginSandboxTenant(TenantDemoProfile $profile, string $password): Company
    {
        return $this->seedVirginTenant($profile, $password);
    }

    private function seedDemonstrationTenant(TenantDemoProfile $profile, string $password): Company
    {
        $company = Company::query()->updateOrCreate(
            ['name' => $profile->companyName],
            [
                'legal_name' => $profile->companyLegalName,
                'currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
                'form_max_image_size_kb' => 2048,
                'form_allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
                'fiscal_provider' => config('phoenix.billing.fiscal.default_provider', 'sandbox'),
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

        $combustibleTipoCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'tipo-combustible-vehiculo'],
            [
                'name' => 'Tipo de combustible — vehículo',
                'options' => [
                    ['value' => 'gasolina', 'label' => 'Gasolina', 'description' => 'Motor de gasolina.'],
                    ['value' => 'diesel', 'label' => 'Diésel', 'description' => 'Motor diésel / DI-D.'],
                ],
            ]
        );

        $traccionCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'traccion-vehiculo'],
            [
                'name' => 'Tracción — vehículo',
                'options' => [
                    ['value' => '4x2', 'label' => '4x2 (trasera)', 'description' => 'Tracción trasera.'],
                    ['value' => '4x4', 'label' => '4x4', 'description' => 'Tracción en las cuatro ruedas.'],
                ],
            ]
        );

        $posicionFiltroCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'posicion-filtro'],
            [
                'name' => 'Posición — filtro',
                'options' => [
                    ['value' => 'aceite', 'label' => 'Aceite', 'description' => 'Filtro de aceite motor.'],
                    ['value' => 'aire', 'label' => 'Aire motor', 'description' => 'Filtro de aire de admisión.'],
                    ['value' => 'habitaculo', 'label' => 'Habitáculo', 'description' => 'Filtro de cabina / polen.'],
                    ['value' => 'combustible', 'label' => 'Combustible', 'description' => 'Filtro de combustible.'],
                ],
            ]
        );

        $posicionFrenoCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'posicion-freno'],
            [
                'name' => 'Posición — frenos / balatas',
                'options' => [
                    ['value' => 'delanteras', 'label' => 'Delanteras', 'description' => 'Eje delantero.'],
                    ['value' => 'traseras', 'label' => 'Traseras', 'description' => 'Eje trasero.'],
                ],
            ]
        );

        $posicionSuspensionCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'posicion-suspension'],
            [
                'name' => 'Posición — suspensión',
                'options' => [
                    ['value' => 'delanteros', 'label' => 'Delanteros', 'description' => 'Eje delantero.'],
                    ['value' => 'traseros', 'label' => 'Traseros', 'description' => 'Eje trasero.'],
                ],
            ]
        );

        $unidadInsumoCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => SupplyUnits::CATALOG_SLUG],
            [
                'name' => 'Unidad — insumo',
                'options' => SupplyUnits::OPTIONS,
            ]
        );

        // Retirar catálogo legacy duplicado (presentación = unidad).
        FormOptionCatalog::query()
            ->where('company_id', $company->id)
            ->where('slug', 'presentacion-insumo')
            ->delete();

        $tecnologiaAmortiguadorCatalog = FormOptionCatalog::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'tecnologia-amortiguador'],
            [
                'name' => 'Tecnología — amortiguador',
                'options' => [
                    ['value' => 'gas', 'label' => 'Gas', 'description' => 'Amortiguador de gas.'],
                    ['value' => 'aceite', 'label' => 'Aceite', 'description' => 'Amortiguador hidráulico.'],
                    ['value' => 'gas_aceite', 'label' => 'Gas / aceite', 'description' => 'Combinado.'],
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
            ['company_id' => $company->id, 'code' => $profile->clientCode],
            [
                'legal_name' => $profile->clientLegalName,
                'trade_name' => $profile->clientTradeName,
                'tax_id' => 'APE850101ABC',
                'billing_email' => 'facturacion@clientepremium.example',
                'is_active' => true,
            ]
        );
        $this->seedDemoClientLogo($demoClient);
        $this->seedExtraDemoClients($company, $profile);

        $site = Site::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => $profile->siteName],
            [
                'address' => 'Av. Reforma 2500, CDMX',
                'client_id' => $demoClient->id,
            ]
        );

        $admin = null;
        foreach ($profile->staff as $staffRow) {
            $user = User::query()->updateOrCreate(
                ['email' => $staffRow['email']],
                ['name' => $staffRow['name'], 'password' => $password],
            );
            if ($staffRow['role'] === MembershipRole::Administrator) {
                $admin = $user;
            }
            $clientId = ! empty($staffRow['portal_client']) ? $demoClient->id : null;
            CompanyMembership::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['role' => $staffRow['role'], 'is_active' => true, 'client_id' => $clientId],
            );
        }

        if ($admin === null) {
            throw new \RuntimeException('Tenant demo profile must include an administrator.');
        }

        $normalizedFormDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'inspeccion-vehiculo-v1'],
            ['name' => 'Inspección vehículo (normalizada)', 'usage' => FormUsage::Service]
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

        $fichaVehiculoFormDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'ficha-tecnica-vehiculo-v1'],
            ['name' => 'Ficha técnica vehículo', 'usage' => FormUsage::Article]
        );

        $fichaVehiculoFormVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $fichaVehiculoFormDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => [
                    'sections' => VehicleRegistrationFormSchema::sections(
                        $combustibleTipoCatalog->id,
                        $traccionCatalog->id,
                    ),
                ],
            ]
        );

        DemoDesignDraftVersions::ensureFormDraft($fichaVehiculoFormDef, $fichaVehiculoFormVersion, $admin);

        $formFiltrosDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'ficha-insumo-filtros-v1'],
            ['name' => 'Ficha insumo — filtros', 'usage' => FormUsage::Inventory]
        );
        $formFiltrosVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $formFiltrosDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => NormalizedSupplyFormSchemas::filtros(
                    $posicionFiltroCatalog->id,
                    $unidadInsumoCatalog->id,
                ),
            ]
        );
        DemoDesignDraftVersions::ensureFormDraft($formFiltrosDef, $formFiltrosVersion, $admin);

        $formFrenosDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'ficha-insumo-frenos-v1'],
            ['name' => 'Ficha insumo — frenos y balatas', 'usage' => FormUsage::Inventory]
        );
        $formFrenosVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $formFrenosDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => NormalizedSupplyFormSchemas::frenos(
                    $posicionFrenoCatalog->id,
                    $unidadInsumoCatalog->id,
                ),
            ]
        );
        DemoDesignDraftVersions::ensureFormDraft($formFrenosDef, $formFrenosVersion, $admin);

        $formSuspensionDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'ficha-insumo-suspension-v1'],
            ['name' => 'Ficha insumo — suspensión', 'usage' => FormUsage::Inventory]
        );
        $formSuspensionVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $formSuspensionDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => NormalizedSupplyFormSchemas::suspension(
                    $posicionSuspensionCatalog->id,
                    $tecnologiaAmortiguadorCatalog->id,
                    $unidadInsumoCatalog->id,
                ),
            ]
        );
        DemoDesignDraftVersions::ensureFormDraft($formSuspensionDef, $formSuspensionVersion, $admin);

        $formFluidosDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'ficha-insumo-fluidos-v1'],
            ['name' => 'Ficha insumo — fluidos y lubricantes', 'usage' => FormUsage::Inventory]
        );
        $formFluidosVersion = FormVersion::query()->updateOrCreate(
            ['form_definition_id' => $formFluidosDef->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'schema' => NormalizedSupplyFormSchemas::fluidos($unidadInsumoCatalog->id),
            ]
        );
        DemoDesignDraftVersions::ensureFormDraft($formFluidosDef, $formFluidosVersion, $admin);

        $typeVehiculo = EquipmentType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'vehiculo'],
            [
                'name' => 'Vehículo',
                'description' => 'Automóviles y camionetas',
                'sort_order' => 1,
                'default_form_definition_id' => $fichaVehiculoFormDef->id,
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
            [
                'name' => 'Filtros',
                'sort_order' => 1,
                'default_form_definition_id' => $formFiltrosDef->id,
            ]
        );
        $supplyTypeFrenos = SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'frenos'],
            [
                'name' => 'Frenos y balatas',
                'sort_order' => 2,
                'default_form_definition_id' => $formFrenosDef->id,
            ]
        );
        $supplyTypeSuspension = SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'suspension'],
            [
                'name' => 'Suspensión',
                'sort_order' => 3,
                'default_form_definition_id' => $formSuspensionDef->id,
            ]
        );
        SupplyType::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'fluidos'],
            [
                'name' => 'Fluidos y lubricantes',
                'sort_order' => 4,
                'default_form_definition_id' => $formFluidosDef->id,
            ]
        );

        $catalog = CatalogItem::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $profile->catalogCode],
            [
                'equipment_type_id' => $typeVehiculo->id,
                'name' => $profile->catalogName,
                'manufacturer' => 'Mitsubishi',
                'specifications' => [
                    'modelo' => 'L200',
                    'anio' => 2018,
                    'mercado' => 'México',
                    'chasis' => 'Rise Body',
                    'variante' => '2.5L DI-D 4x4',
                    'tipo_combustible' => 'diesel',
                    'motor' => '2.5 Litros, 4 cilindros Turbo Diésel (Intercooler)',
                    'potencia_hp' => 134,
                    'potencia_rpm' => 4000,
                    'torque_lb_pie' => 232,
                    'torque_rpm' => 2000,
                    'transmision' => 'Manual de 5 velocidades (con caja reductora)',
                    'traccion' => '4x4',
                    'alimentacion' => 'Inyección Directa Common Rail de Alta Presión',
                    'suspension_delantera' => 'Independiente de doble horquilla con resortes helicoidales y barra estabilizadora',
                    'suspension_trasera' => 'Eje rígido con muelles elípticos (ballestas) reforzadas para carga',
                    'frenos_delanteros' => 'Discos ventilados',
                    'frenos_traseros' => 'Tambor',
                    'asistencias' => 'ABS y EBD',
                    'direccion' => 'Hidráulica asistida de piñón y cremallera',
                    'largo_mm' => 5205,
                    'ancho_mm' => 1785,
                    'alto_mm' => 1775,
                    'batalla_mm' => 3000,
                    'capacidad_carga_kg' => 1050,
                    'tanque_litros' => 75,
                    'rines' => 'Aluminio de 16 pulgadas',
                ],
            ]
        );

        $asset = Asset::query()->updateOrCreate(
            ['company_id' => $company->id, 'tag' => $profile->assetTag],
            [
                'client_id' => $demoClient->id,
                'site_id' => $site->id,
                'catalog_item_id' => $catalog->id,
                'base_catalog_item_id' => $catalog->id,
                'sync_mode' => 'linked',
                'serial_number' => $profile->assetSerial,
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

        if (! $profile->isDomG()) {
            SupplyItem::query()
                ->where('company_id', $company->id)
                ->where('sku', 'FIL-ACE-PREM')
                ->delete();
        }

        if (! $profile->isDomG()) {
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
                        'posicion' => 'aceite',
                        'unidad' => 'pza',
                        'notas_mercado' => 'OEM original; rango estimado $500–$600 MXN.',
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
                        'posicion' => 'aire',
                        'unidad' => 'pza',
                        'notas_mercado' => 'Rango estimado $237–$445 MXN.',
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
                        'posicion' => 'delanteras',
                        'material' => 'Cerámicas',
                        'unidad' => 'jgo',
                        'notas_mercado' => 'Rango estimado $1,130–$1,406 MXN.',
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
                        'posicion' => 'delanteros',
                        'tecnologia' => 'gas',
                        'unidad' => 'par',
                        'notas_mercado' => 'Precio estimado $1,698 MXN (par).',
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
        }

        $formDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'revision-mayor-vehiculo-premium'],
            ['name' => 'Revisión mayor vehículo — agencia premium', 'usage' => FormUsage::Service]
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
                'description' => 'Informe alineado al formulario revisión mayor premium',
            ]
        );

        $reportComponents = app(ReportPresetApplier::class)->componentsFromFormVersion($formVersion);
        $reportComponents[] = ['type' => 'divider', 'style' => 'solid', 'margin_pt' => 12];
        $reportComponents[] = ['type' => 'subtitle', 'text' => 'Anexos', 'align' => 'left'];
        $reportComponents[] = ['type' => 'section_template', 'section_template_id' => $sectionAlcance->id, 'align' => 'left'];
        $reportComponents[] = ['type' => 'section_template', 'section_template_id' => $sectionGarantia->id, 'align' => 'left'];

        $reportVersion = ReportTemplateVersion::query()->updateOrCreate(
            ['report_template_id' => $reportTpl->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'components' => $reportComponents,
                'page_settings' => [
                    'size' => 'A4',
                    'font_family' => 'source_sans',
                    'header' => [
                        'enabled' => true,
                        'text' => '{{company}} · Revisión mayor · Servicio #{{routine_id}}',
                    ],
                    'footer' => [
                        'enabled' => true,
                        'text' => 'Documento confidencial — generado por **Phoenix**',
                    ],
                    'page_number' => ['enabled' => true, 'start_at' => 2],
                    'cover_page' => [
                        'enabled' => true,
                        'title' => 'Revisión mayor premium',
                        'subtitle' => '{{company}} · {{asset_tag}}',
                        'body' => 'Inspección y revisión mayor con evidencias por sección: motor, frenos, filtros, suspensión y cierre.',
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
                'service_category' => ServiceCategory::Maintenance,
                'form_version_id' => $formVersion->id,
                'report_template_version_id' => $reportVersion->id,
                'is_active' => true,
            ]
        );

        $workflowDef = app(WorkflowRuntime::class)->seedDefinitionForCompany($company->id);
        $routineType->update(['workflow_definition_id' => $workflowDef->id]);

        foreach ([
            [
                'slug' => 'orden-manufactura',
                'name' => $profile->isDomG() ? 'Orden de producción' : 'Orden de manufactura',
                'line' => ServiceCategory::Manufacturing,
                'legacy_slugs' => ['fabricacion-estructuras'],
            ],
            [
                'slug' => 'suministro-insumos-cliente',
                'name' => 'Suministro de insumos a cliente',
                'line' => ServiceCategory::Installation,
                'legacy_slugs' => [],
            ],
        ] as $extraType) {
            if ($extraType['legacy_slugs'] !== []) {
                RoutineType::query()
                    ->where('company_id', $company->id)
                    ->whereIn('slug', $extraType['legacy_slugs'])
                    ->update([
                        'slug' => $extraType['slug'],
                        'name' => $extraType['name'],
                        'service_category' => $extraType['line'],
                    ]);
            }

            RoutineType::query()->updateOrCreate(
                ['company_id' => $company->id, 'slug' => $extraType['slug']],
                [
                    'name' => $extraType['name'],
                    'service_category' => $extraType['line'],
                    'form_version_id' => $formVersion->id,
                    'report_template_version_id' => $reportVersion->id,
                    'workflow_definition_id' => $workflowDef->id,
                    'is_active' => true,
                ]
            );
        }

        AssetClientAssignment::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'asset_id' => $asset->id,
                'client_id' => $demoClient->id,
                'unassigned_at' => null,
            ],
            [
                'serial_number' => $asset->serial_number ?? $profile->assetSerial,
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ]
        );

        foreach ([
            [
                'sku' => 'FLT-AIR-01',
                'supply_type_id' => $supplyTypeFiltros->id,
                'name' => 'Filtro de aire motor',
                'sector' => 'mechanical',
                'material_kind' => 'spare_part',
                'unit' => 'pza',
                'standard_cost' => 320.00,
                'quantity_on_hand' => 18,
                'min_stock' => 6,
                'storage_location' => 'Rack M-12',
                'is_active' => true,
            ],
            [
                'sku' => 'LUB-5W30',
                'supply_type_id' => $supplyTypeFiltros->id,
                'name' => 'Aceite sintético 5W-30',
                'sector' => 'mechanical',
                'material_kind' => 'chemical',
                'unit' => 'lt',
                'standard_cost' => 180.00,
                'quantity_on_hand' => 42,
                'min_stock' => 15,
                'storage_location' => 'Bodega fluidos',
                'is_active' => true,
            ],
            [
                'sku' => 'EPP-GLOVE',
                'supply_type_id' => $supplyTypeFiltros->id,
                'name' => 'Guantes nitrilo industrial',
                'sector' => 'safety',
                'material_kind' => 'consumable',
                'unit' => 'pqt',
                'standard_cost' => 95.00,
                'quantity_on_hand' => 8,
                'min_stock' => 10,
                'storage_location' => 'EPP entrada',
                'is_active' => true,
            ],
        ] as $stockSeed) {
            SupplyItem::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $stockSeed['sku']],
                array_merge($stockSeed, ['supplier_id' => $supplier->id]),
            );
        }

        $this->ensureDemonstrationRoutine($company, $profile);

        return $company;
    }

    private function ensureDemonstrationRoutine(Company $company, TenantDemoProfile $profile): void
    {
        $existingDemo = Routine::query()
            ->where('company_id', $company->id)
            ->where('is_demo', true)
            ->first();

        $technicianEmail = null;
        foreach ($profile->staff as $staffRow) {
            if ($staffRow['role'] === MembershipRole::Technician) {
                $technicianEmail = $staffRow['email'];
                break;
            }
        }

        if ($technicianEmail === null) {
            return;
        }

        $technician = User::query()->where('email', $technicianEmail)->first();
        if ($technician === null) {
            return;
        }

        $factory = app(DemoRoutineFactory::class);

        if ($existingDemo !== null) {
            $factory->refreshDemoResponses($existingDemo);

            return;
        }

        $factory->createForCompany($company->id, $technician);
    }

    private function seedExtraDemoClients(Company $company, TenantDemoProfile $profile): void
    {
        // Sandbox: solo el cliente primario del playground (ya creado arriba).
        if ($profile->isSandbox()) {
            return;
        }

        $extras = [
            [
                'code' => $profile->isDomG() ? 'DOMG-INTERNO' : 'MEIN-INTERNO',
                'trade_name' => 'Interno',
                'legal_name' => $profile->isDomG()
                    ? 'Trabajos internos Dom-G'
                    : 'Trabajos internos Mein Company',
            ],
        ];

        foreach ($extras as $row) {
            Client::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $row['code']],
                [
                    'legal_name' => $row['legal_name'],
                    'trade_name' => $row['trade_name'],
                    'tax_id' => null,
                    'billing_email' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedPlatformCatalogCompany(): void
    {
        $company = Company::query()->updateOrCreate(
            ['name' => PlatformCatalogCompany::NAME],
            [
                'legal_name' => PlatformCatalogCompany::NAME,
                'currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
                'is_active' => true,
            ],
        );

        // Retirar plantillas de manufactura / tipos vacíos de iteraciones anteriores.
        CatalogItem::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereIn('code', ['SYS-DOMO-01', 'SYS-ESC-01', 'SYS-CIVIL-01'])
            ->delete();
        EquipmentType::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereIn('code', ['SYS-STRUCT', 'SYS-MOTOR', 'SYS-PUMP'])
            ->delete();

        $typeVehicle = EquipmentType::withoutGlobalScope('company')->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SYS-VEHICLE'],
            [
                'name' => 'Vehículo',
                'description' => 'Plantillas de vehículos ligeros / utilitarios',
                'sort_order' => 0,
            ],
        );

        $typeByClass = [
            'SCOOPTRAM' => EquipmentType::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SYS-LHD'],
                [
                    'name' => 'Cargador LHD',
                    'description' => 'Scooptram / Toro — carga y acarreo subterráneo',
                    'sort_order' => 1,
                ],
            ),
            'CAMION_BAJO_PERFIL' => EquipmentType::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SYS-TRUCK'],
                [
                    'name' => 'Camión minero',
                    'description' => 'Minetruck / Toro TH — acarreo de bajo perfil',
                    'sort_order' => 2,
                ],
            ),
            'JUMBO' => EquipmentType::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'code' => 'SYS-DRILL'],
                [
                    'name' => 'Perforadora',
                    'description' => 'Jumbos, taladros largos y empernadoras',
                    'sort_order' => 3,
                ],
            ),
        ];

        CatalogItem::withoutGlobalScope('company')->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'SYS-L200-2018'],
            [
                'is_system_template' => true,
                'equipment_type_id' => $typeVehicle->id,
                'name' => 'Mitsubishi L200 2018',
                'manufacturer' => 'Mitsubishi',
                'specifications' => [
                    'modelo' => 'L200',
                    'anio' => 2018,
                    'mercado' => 'México',
                    'chasis' => 'Rise Body',
                    'variante' => '2.5L DI-D 4x4',
                    'tipo_combustible' => 'diesel',
                    'motor' => '2.5 Litros, 4 cilindros Turbo Diésel (Intercooler)',
                    'potencia_hp' => 134,
                    'torque_lb_pie' => 232,
                    'transmision' => 'Manual de 5 velocidades (con caja reductora)',
                    'traccion' => '4x4',
                ],
            ],
        );

        $oemModels = \App\Models\OemEquipmentModel::query()
            ->whereIn('manufacturer', ['Epiroc', 'Sandvik'])
            ->orderBy('manufacturer')
            ->orderBy('family')
            ->orderBy('model')
            ->get();

        $keepCodes = ['SYS-L200-2018'];

        foreach ($oemModels as $oem) {
            $class = (string) $oem->equipment_class;
            $type = $typeByClass[$class] ?? null;
            if ($type === null) {
                continue;
            }

            $code = \Illuminate\Support\Str::limit(
                'SYS-'.\Illuminate\Support\Str::upper(
                    \Illuminate\Support\Str::slug($oem->manufacturer.' '.$oem->model, '-')
                ),
                64,
                '',
            );
            $keepCodes[] = $code;

            $specs = is_array($oem->specifications) ? $oem->specifications : [];
            CatalogItem::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'is_system_template' => true,
                    'equipment_type_id' => $type->id,
                    'name' => trim($oem->manufacturer.' '.$oem->model),
                    'manufacturer' => $oem->manufacturer,
                    'oem_equipment_model_id' => $oem->id,
                    'specifications' => array_filter([
                        'modelo' => $oem->model,
                        'family' => $oem->family,
                        'equipment_class' => $class,
                        'application' => $oem->application,
                        'description' => $oem->description,
                        'source_url' => $oem->source_url,
                        ...$specs,
                    ], fn ($v) => $v !== null && $v !== ''),
                ],
            );
        }

        // Limpiar plantillas huérfanas (p. ej. códigos viejos) que ya no forman parte del catálogo.
        CatalogItem::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_system_template', true)
            ->whereNotIn('code', $keepCodes)
            ->delete();
    }

    private function seedDemoClientLogo(Client $client): void
    {
        $relativePath = DemoClientLogoGenerator::syncForClient($client->id);
        if ($relativePath !== '') {
            $client->update(['logo_path' => $relativePath]);
        }
    }
}
