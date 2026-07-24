<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Enums\RoutineStatus;
use App\Models\Asset;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\SupplyItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NoahDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->create([
            'name' => 'Demo Industrial',
            'legal_name' => 'Demo Industrial S.A. de C.V.',
            'currency' => 'MXN',
        ]);

        $site = Site::query()->create([
            'company_id' => $company->id,
            'name' => 'Planta Norte',
            'address' => 'Av. Industria 100',
        ]);

        $admin = User::query()->create([
            'name' => 'Administrador Noah',
            'email' => 'admin@noah.local',
            'password' => Hash::make('password'),
        ]);

        $technician = User::query()->create([
            'name' => 'Técnico Demo',
            'email' => 'tecnico@noah.local',
            'password' => Hash::make('password'),
        ]);

        $supervisor = User::query()->create([
            'name' => 'Supervisor Demo',
            'email' => 'supervisor@noah.local',
            'password' => Hash::make('password'),
        ]);

        $billing = User::query()->create([
            'name' => 'Facturación Demo',
            'email' => 'facturacion@noah.local',
            'password' => Hash::make('password'),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => MembershipRole::Administrator,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $technician->id,
            'role' => MembershipRole::Technician,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $supervisor->id,
            'role' => MembershipRole::Supervisor,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $billing->id,
            'role' => MembershipRole::Billing,
        ]);

        \App\Models\PromptTemplate::query()->create([
            'company_id' => null,
            'slug' => 'grammar_correction_v1',
            'version' => 1,
            'provider' => 'local',
            'system_prompt' => 'Eres un corrector de textos técnicos. No agregues información nueva.',
            'user_template' => "{{technician_text}}",
            'is_active' => true,
        ]);

        $catalog = CatalogItem::query()->create([
            'company_id' => $company->id,
            'code' => 'COMP-001',
            'name' => 'Compresor de aire 50HP',
            'manufacturer' => 'Atlas',
        ]);

        $asset = Asset::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'catalog_item_id' => $catalog->id,
            'tag' => 'EQ-1001',
            'serial_number' => 'SN-998877',
            'location_label' => 'Sala de máquinas',
        ]);

        SupplyItem::query()->create([
            'company_id' => $company->id,
            'sku' => 'FIL-001',
            'name' => 'Filtro de aceite',
            'unit' => 'pza',
            'standard_cost' => 450.00,
        ]);

        $formDef = FormDefinition::query()->create([
            'company_id' => $company->id,
            'name' => 'Mantenimiento preventivo',
            'slug' => 'mantenimiento-preventivo',
        ]);

        $formVersion = FormVersion::query()->create([
            'form_definition_id' => $formDef->id,
            'version' => 1,
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->id,
            'schema' => [
                'sections' => [
                    [
                        'title' => 'Datos generales',
                        'fields' => [
                            ['key' => 'horometro', 'type' => 'number', 'label' => 'Horómetro'],
                            ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones'],
                        ],
                    ],
                ],
            ],
        ]);

        $reportTpl = ReportTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'Reporte estándar',
            'slug' => 'reporte-estandar',
        ]);

        $reportVersion = ReportTemplateVersion::query()->create([
            'report_template_id' => $reportTpl->id,
            'version' => 1,
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->id,
            'components' => [
                ['type' => 'title', 'text' => 'Reporte de mantenimiento'],
                ['type' => 'paragraph', 'field' => 'corrected_comments'],
            ],
            'page_settings' => ['size' => 'A4'],
        ]);

        $routineType = RoutineType::query()->create([
            'company_id' => $company->id,
            'name' => 'Preventivo compresor',
            'slug' => 'preventivo-compresor',
            'form_version_id' => $formVersion->id,
            'report_template_version_id' => $reportVersion->id,
            'is_active' => true,
        ]);

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
}
