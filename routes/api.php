<?php

use App\Http\Controllers\Api\V1\AssetClientAssignmentController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\BillingSettingsController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientPortalController;
use App\Http\Controllers\Api\V1\CompanyRoleController;
use App\Http\Controllers\Api\V1\CompanyUserController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExecutionEvidenceController;
use App\Http\Controllers\Api\V1\CatalogItemController;
use App\Http\Controllers\Api\V1\EquipmentTypeController;
use App\Http\Controllers\Api\V1\FormDefinitionController;
use App\Http\Controllers\Api\V1\FormDesignSettingsController;
use App\Http\Controllers\Api\V1\FormOptionCatalogController;
use App\Http\Controllers\Api\V1\GeneratedReportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\RoutineExecutionController;
use App\Http\Controllers\Api\V1\RoutineFormFieldUploadController;
use App\Http\Controllers\Api\V1\RoutineTypeController;
use App\Http\Controllers\Api\V1\ReportSectionTemplateController;
use App\Http\Controllers\Api\V1\ReportTemplateController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\SupplyItemController;
use App\Http\Controllers\Api\V1\SupplyTypeController;
use App\Http\Controllers\Api\V1\WorkflowDefinitionController;
use App\Support\DemoEnvironmentBootstrap;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        DemoEnvironmentBootstrap::ensureAccountsIfMissing();

        $payload = [
            'status' => 'ok',
            'message' => 'Noah API',
            'product' => 'noah',
        ];

        if (app()->environment('local')) {
            $payload['demo'] = [
                'accounts_ready' => \App\Models\User::query()->where('email', 'admin@noah.local')->exists(),
                'password' => config('noah.demo_password'),
            ];
        }

        return response()->json($payload);
    });

    Route::get('/portal', [PortalController::class, 'show']);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/avatar', [ProfileController::class, 'updateAvatar']);

        Route::middleware('platform.admin')->prefix('platform')->group(function (): void {
            Route::get('/role-permissions', [\App\Http\Controllers\Api\V1\PlatformRolePermissionController::class, 'show']);
            Route::put('/role-permissions', [\App\Http\Controllers\Api\V1\PlatformRolePermissionController::class, 'update']);
        });

        Route::middleware('company')->group(function (): void {
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

            Route::get('/sites', [SiteController::class, 'index'])
                ->middleware('company.module:sites,read');
            Route::post('/sites', [SiteController::class, 'store'])
                ->middleware('company.module:sites,write');
            Route::put('/sites/{site}', [SiteController::class, 'update'])
                ->middleware('company.module:sites,write');
            Route::delete('/sites/{site}', [SiteController::class, 'destroy'])
                ->middleware('company.module:sites,write');

            Route::get('/catalog/suppliers', [SupplierController::class, 'index'])
                ->middleware('company.module:catalog_suppliers,read');
            Route::post('/catalog/suppliers', [SupplierController::class, 'store'])
                ->middleware('company.module:catalog_suppliers,write');
            Route::put('/catalog/suppliers/{supplier}', [SupplierController::class, 'update'])
                ->middleware('company.module:catalog_suppliers,write');
            Route::delete('/catalog/suppliers/{supplier}', [SupplierController::class, 'destroy'])
                ->middleware('company.module:catalog_suppliers,write');

            Route::get('/clients', [ClientController::class, 'index'])
                ->middleware('company.module:clients,read');
            Route::get('/clients/{client}', [ClientController::class, 'show'])
                ->middleware('company.module:clients,read');
            Route::post('/clients', [ClientController::class, 'store'])
                ->middleware('company.module:clients,write');
            Route::put('/clients/{client}', [ClientController::class, 'update'])
                ->middleware('company.module:clients,write');
            Route::post('/clients/{client}/logo', [ClientController::class, 'updateLogo'])
                ->middleware('company.module:clients,write');
            Route::delete('/clients/{client}', [ClientController::class, 'destroy'])
                ->middleware('company.module:clients,write');

            Route::middleware('company.role:administrator')->group(function (): void {
                Route::get('/company/users', [CompanyUserController::class, 'index']);
                Route::post('/company/users', [CompanyUserController::class, 'store']);
                Route::put('/company/users/{user}', [CompanyUserController::class, 'update']);
                Route::get('/company/roles', [CompanyRoleController::class, 'index']);
                Route::get('/portal/settings', [PortalController::class, 'show']);
                Route::put('/portal/settings', [PortalController::class, 'update']);
            });

            Route::post('/sync', [SyncController::class, 'sync']);

            Route::get('/catalog/equipment-types/form-options', [EquipmentTypeController::class, 'formOptions'])
                ->middleware('company.module:catalog_items,read');
            Route::get('/catalog/equipment-types/{equipmentType}/form-capture', [EquipmentTypeController::class, 'formCapture'])
                ->middleware('company.module:catalog_items,read');
            Route::get('/catalog/equipment-types', [EquipmentTypeController::class, 'index'])
                ->middleware('company.module:catalog_items,read');
            Route::post('/catalog/equipment-types', [EquipmentTypeController::class, 'store'])
                ->middleware('company.module:catalog_items,write');
            Route::put('/catalog/equipment-types/{equipmentType}', [EquipmentTypeController::class, 'update'])
                ->middleware('company.module:catalog_items,write');
            Route::delete('/catalog/equipment-types/{equipmentType}', [EquipmentTypeController::class, 'destroy'])
                ->middleware('company.module:catalog_items,write');

            Route::get('/catalog/supply-types/form-options', [SupplyTypeController::class, 'formOptions'])
                ->middleware('company.module:catalog_supplies,read');
            Route::get('/catalog/supply-types/{supplyType}/form-capture', [SupplyTypeController::class, 'formCapture'])
                ->middleware('company.module:catalog_supplies,read');
            Route::get('/catalog/supply-types', [SupplyTypeController::class, 'index'])
                ->middleware('company.module:catalog_supplies,read');
            Route::post('/catalog/supply-types', [SupplyTypeController::class, 'store'])
                ->middleware('company.module:catalog_supplies,write');
            Route::put('/catalog/supply-types/{supplyType}', [SupplyTypeController::class, 'update'])
                ->middleware('company.module:catalog_supplies,write');
            Route::delete('/catalog/supply-types/{supplyType}', [SupplyTypeController::class, 'destroy'])
                ->middleware('company.module:catalog_supplies,write');

            Route::get('/catalog/items', [CatalogItemController::class, 'index'])
                ->middleware('company.module:catalog_items,read');
            Route::post('/catalog/items', [CatalogItemController::class, 'store'])
                ->middleware('company.module:catalog_items,write');
            Route::put('/catalog/items/{catalogItem}', [CatalogItemController::class, 'update'])
                ->middleware('company.module:catalog_items,write');
            Route::delete('/catalog/items/{catalogItem}', [CatalogItemController::class, 'destroy'])
                ->middleware('company.module:catalog_items,write');

            Route::get('/inventory/supplies', [SupplyItemController::class, 'index'])
                ->middleware('company.module:catalog_supplies,read');
            Route::post('/inventory/supplies', [SupplyItemController::class, 'store'])
                ->middleware('company.module:catalog_supplies,write');
            Route::put('/inventory/supplies/{supplyItem}', [SupplyItemController::class, 'update'])
                ->middleware('company.module:catalog_supplies,write');
            Route::delete('/inventory/supplies/{supplyItem}', [SupplyItemController::class, 'destroy'])
                ->middleware('company.module:catalog_supplies,write');

            Route::get('/assets', [AssetController::class, 'index'])
                ->middleware('company.module:assets,read');
            Route::post('/assets', [AssetController::class, 'store'])
                ->middleware('company.module:assets,write');
            Route::put('/assets/{asset}', [AssetController::class, 'update'])
                ->middleware('company.module:assets,write');
            Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])
                ->middleware('company.module:assets,write');
            Route::get('/assets/{asset}/client-assignments', [AssetClientAssignmentController::class, 'index'])
                ->middleware('company.module:assets,read');
            Route::post('/assets/{asset}/client-assignments', [AssetClientAssignmentController::class, 'store'])
                ->middleware('company.module:assets,write');

            Route::middleware('company.client_portal')->prefix('portal')->group(function (): void {
                Route::get('/invoices', [ClientPortalController::class, 'invoices']);
                Route::get('/invoices/{invoice}', [ClientPortalController::class, 'showInvoice']);
                Route::get('/invoices/{invoice}/download', [ClientPortalController::class, 'downloadInvoice']);
                Route::get('/assets', [ClientPortalController::class, 'assets']);
                Route::get('/routines', [ClientPortalController::class, 'routines']);
                Route::get('/routines/{routine}', [ClientPortalController::class, 'showRoutine']);
            });

            Route::get('/design/forms/settings', [FormDesignSettingsController::class, 'show'])
                ->middleware('company.module:design_forms,read');
            Route::put('/design/forms/settings', [FormDesignSettingsController::class, 'update'])
                ->middleware('company.module:design_forms,write');
            Route::get('/design/forms/option-catalogs', [FormOptionCatalogController::class, 'index'])
                ->middleware('company.module:design_forms,read');
            Route::post('/design/forms/option-catalogs', [FormOptionCatalogController::class, 'store'])
                ->middleware('company.module:design_forms,write');
            Route::put('/design/forms/option-catalogs/{optionCatalog}', [FormOptionCatalogController::class, 'update'])
                ->middleware('company.module:design_forms,write');
            Route::delete('/design/forms/option-catalogs/{optionCatalog}', [FormOptionCatalogController::class, 'destroy'])
                ->middleware('company.module:design_forms,write');

            Route::get('/design/forms', [FormDefinitionController::class, 'index'])
                ->middleware('company.module:design_forms,read');
            Route::post('/design/forms', [FormDefinitionController::class, 'store'])
                ->middleware('company.module:design_forms,write');
            Route::get('/design/forms/{form}', [FormDefinitionController::class, 'show'])
                ->middleware('company.module:design_forms,read');
            Route::put('/design/forms/{form}/schema', [FormDefinitionController::class, 'updateSchema'])
                ->middleware('company.module:design_forms,write');
            Route::post('/design/forms/{form}/publish', [FormDefinitionController::class, 'publish'])
                ->middleware('company.module:design_forms,write');
            Route::delete('/design/forms/{form}', [FormDefinitionController::class, 'destroy'])
                ->middleware('company.module:design_forms,write');

            Route::get('/design/reports', [ReportTemplateController::class, 'index'])
                ->middleware('company.module:design_reports,read');
            Route::post('/design/reports', [ReportTemplateController::class, 'store'])
                ->middleware('company.module:design_reports,write');
            Route::get('/design/reports/section-templates', [ReportSectionTemplateController::class, 'index'])
                ->middleware('company.module:design_reports,read');
            Route::post('/design/reports/section-templates', [ReportSectionTemplateController::class, 'store'])
                ->middleware('company.module:design_reports,write');
            Route::put('/design/reports/section-templates/{reportSectionTemplate}', [ReportSectionTemplateController::class, 'update'])
                ->middleware('company.module:design_reports,write');
            Route::delete('/design/reports/section-templates/{reportSectionTemplate}', [ReportSectionTemplateController::class, 'destroy'])
                ->middleware('company.module:design_reports,write');
            Route::get('/design/reports/{reportTemplate}/preview', [ReportTemplateController::class, 'preview'])
                ->middleware('company.module:design_reports,read');
            Route::post('/design/reports/{reportTemplate}/preview', [ReportTemplateController::class, 'previewDraft'])
                ->middleware('company.module:design_reports,read');
            Route::put('/design/reports/{reportTemplate}', [ReportTemplateController::class, 'update'])
                ->middleware('company.module:design_reports,write');
            Route::get('/design/reports/{reportTemplate}', [ReportTemplateController::class, 'show'])
                ->middleware('company.module:design_reports,read');
            Route::put('/design/reports/{reportTemplate}/components', [ReportTemplateController::class, 'updateComponents'])
                ->middleware('company.module:design_reports,write');
            Route::post('/design/reports/{reportTemplate}/cover-image', [ReportTemplateController::class, 'uploadCoverImage'])
                ->middleware('company.module:design_reports,write');
            Route::delete('/design/reports/{reportTemplate}/cover-image', [ReportTemplateController::class, 'deleteCoverImage'])
                ->middleware('company.module:design_reports,write');
            Route::post('/design/reports/{reportTemplate}/publish', [ReportTemplateController::class, 'publish'])
                ->middleware('company.module:design_reports,write');

            Route::get('/design/workflows', [WorkflowDefinitionController::class, 'index'])
                ->middleware('company.module:design_workflows,read');
            Route::get('/design/workflows/templates', [WorkflowDefinitionController::class, 'templates'])
                ->middleware('company.module:design_workflows,read');
            Route::post('/design/workflows', [WorkflowDefinitionController::class, 'store'])
                ->middleware('company.module:design_workflows,write');
            Route::get('/design/workflows/{workflowDefinition}', [WorkflowDefinitionController::class, 'show'])
                ->middleware('company.module:design_workflows,read');
            Route::patch('/design/workflows/{workflowDefinition}', [WorkflowDefinitionController::class, 'update'])
                ->middleware('company.module:design_workflows,write');
            Route::put('/design/workflows/{workflowDefinition}/configure', [WorkflowDefinitionController::class, 'configure'])
                ->middleware('company.module:design_workflows,write');
            Route::post('/design/workflows/{workflowDefinition}/publish', [WorkflowDefinitionController::class, 'publish'])
                ->middleware('company.module:design_workflows,write');
            Route::post('/design/workflows/{workflowDefinition}/duplicate', [WorkflowDefinitionController::class, 'duplicate'])
                ->middleware('company.module:design_workflows,write');
            Route::put('/design/workflows/{workflowDefinition}/definition', [WorkflowDefinitionController::class, 'updateDefinition'])
                ->middleware('company.module:design_workflows,write');
            Route::delete('/design/workflows/{workflowDefinition}', [WorkflowDefinitionController::class, 'destroy'])
                ->middleware('company.module:design_workflows,write');

            Route::put('/routine-types/{routineType}/workflow', [WorkflowDefinitionController::class, 'updateRoutineTypeWorkflow'])
                ->middleware('company.module:design_workflows,write');

            Route::get('/routine-types', [RoutineTypeController::class, 'index'])
                ->middleware('company.module:design_routine_types,read');
            Route::post('/routine-types', [RoutineTypeController::class, 'store'])
                ->middleware('company.module:design_routine_types,write');
            Route::put('/routine-types/{routineType}', [RoutineTypeController::class, 'update'])
                ->middleware('company.module:design_routine_types,write');
            Route::delete('/routine-types/{routineType}', [RoutineTypeController::class, 'destroy'])
                ->middleware('company.module:design_routine_types,write');
            Route::put('/routine-types/{routineType}/design', [RoutineTypeController::class, 'updateDesign'])
                ->middleware('company.module:design_routine_types,write');

            Route::get('/routines', [RoutineController::class, 'index'])
                ->middleware('company.module:routines,read');
            Route::post('/routines', [RoutineController::class, 'store'])
                ->middleware('company.module:routines,write');
            Route::post('/routines/demo', [RoutineController::class, 'storeDemo'])
                ->middleware('company.module:routines,write');
            Route::get('/routines/{routine}', [RoutineController::class, 'show'])
                ->middleware('company.module:routines,read');
            Route::post('/routines/{routine}/form-field-upload', [RoutineFormFieldUploadController::class, 'store'])
                ->middleware('company.permission:routines.execute,routines.assign');
            Route::post('/routines/{routine}/executions', [RoutineExecutionController::class, 'store'])
                ->middleware('company.permission:routines.execute,routines.assign');
            Route::post('/routines/{routine}/evidences', [ExecutionEvidenceController::class, 'store'])
                ->middleware('company.permission:routines.execute,routines.assign');
            Route::post('/routines/{routine}/validate', [RoutineExecutionController::class, 'validateExecution'])
                ->middleware('company.permission:routines.validate');
            Route::post('/routines/{routine}/reject', [RoutineExecutionController::class, 'reject'])
                ->middleware('company.permission:routines.validate');

            Route::get('/routines/{routineId}/reports', [GeneratedReportController::class, 'index'])
                ->middleware('company.module:routines,read');
            Route::get('/reports/{report}/download', [GeneratedReportController::class, 'download'])
                ->middleware('company.module:routines,read');
            Route::get('/evidences/{evidence}/download', [ExecutionEvidenceController::class, 'download'])
                ->middleware('company.module:routines,read');

            Route::get('/billing/settings', [BillingSettingsController::class, 'show'])
                ->middleware('company.module:billing,read');
            Route::put('/billing/settings', [BillingSettingsController::class, 'update'])
                ->middleware('company.module:billing,write');

            Route::get('/billing/invoices', [InvoiceController::class, 'index'])
                ->middleware('company.module:billing,read');
            Route::get('/billing/invoices/{invoice}', [InvoiceController::class, 'show'])
                ->middleware('company.module:billing,read');
            Route::put('/billing/invoices/{invoice}/draft', [InvoiceController::class, 'updateDraft'])
                ->middleware('company.module:billing,write');
            Route::post('/billing/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
                ->middleware('company.module:billing,write');

            Route::get('/audit/entries', [AuditController::class, 'index'])
                ->middleware('company.module:audit,read');
        });
    });
});
