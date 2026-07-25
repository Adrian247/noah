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
use App\Models\FormVersion;
use App\Models\PromptTemplate;
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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NoahDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $company = Company::query()->updateOrCreate(
            ['name' => 'Demo Industrial'],
            [
                'legal_name' => 'Demo Industrial S.A. de C.V.',
                'currency' => 'MXN',
                'is_active' => true,
            ]
        );

        $site = Site::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Planta Norte'],
            ['address' => 'Av. Industria 100']
        );

        Client::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'CLI-001'],
            [
                'legal_name' => 'Cliente Industrial Demo S.A. de C.V.',
                'trade_name' => 'Cliente Demo',
                'tax_id' => 'CID850101ABC',
                'billing_email' => 'facturacion@clientedemo.example',
                'is_active' => true,
            ]
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
            ['company_id' => $company->id, 'code' => 'COMP-001'],
            ['name' => 'Compresor de aire 50HP', 'manufacturer' => 'Atlas']
        );

        $asset = Asset::query()->updateOrCreate(
            ['company_id' => $company->id, 'tag' => 'EQ-1001'],
            [
                'site_id' => $site->id,
                'catalog_item_id' => $catalog->id,
                'serial_number' => 'SN-998877',
                'location_label' => 'Sala de máquinas',
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
            ['company_id' => $company->id, 'sku' => 'FIL-001'],
            [
                'name' => 'Filtro de aceite',
                'unit' => 'pza',
                'standard_cost' => 450.00,
                'supplier_id' => $supplier->id,
            ]
        );

        $formDef = FormDefinition::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'mantenimiento-preventivo'],
            ['name' => 'Mantenimiento preventivo']
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
                            'title' => 'Datos generales',
                            'fields' => [
                                ['key' => 'horometro', 'type' => 'number', 'label' => 'Horómetro'],
                                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones'],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $reportTpl = ReportTemplate::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'reporte-estandar'],
            ['name' => 'Reporte estándar']
        );

        $reportVersion = ReportTemplateVersion::query()->updateOrCreate(
            ['report_template_id' => $reportTpl->id, 'version' => 1],
            [
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
                'components' => [
                    ['type' => 'title', 'text' => 'Reporte de mantenimiento'],
                    ['type' => 'paragraph', 'field' => 'corrected_comments'],
                ],
                'page_settings' => ['size' => 'A4'],
            ]
        );

        $routineType = RoutineType::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'preventivo-compresor'],
            [
                'name' => 'Preventivo compresor',
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
    }
}
