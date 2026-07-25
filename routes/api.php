<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\BillingSettingsController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CompanyRoleController;
use App\Http\Controllers\Api\V1\CompanyUserController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExecutionEvidenceController;
use App\Http\Controllers\Api\V1\CatalogItemController;
use App\Http\Controllers\Api\V1\FormDefinitionController;
use App\Http\Controllers\Api\V1\GeneratedReportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\RoutineExecutionController;
use App\Http\Controllers\Api\V1\RoutineTypeController;
use App\Http\Controllers\Api\V1\ReportTemplateController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\SupplyItemController;
use App\Http\Controllers\Api\V1\WorkflowDefinitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'message' => 'Noah API',
        'product' => 'noah',
    ]));

    Route::get('/portal', [PortalController::class, 'show']);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/avatar', [ProfileController::class, 'updateAvatar']);

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

            Route::get('/design/reports', [ReportTemplateController::class, 'index'])
                ->middleware('company.module:design_reports,read');
            Route::post('/design/reports', [ReportTemplateController::class, 'store'])
                ->middleware('company.module:design_reports,write');
            Route::get('/design/reports/{reportTemplate}', [ReportTemplateController::class, 'show'])
                ->middleware('company.module:design_reports,read');
            Route::put('/design/reports/{reportTemplate}/components', [ReportTemplateController::class, 'updateComponents'])
                ->middleware('company.module:design_reports,write');
            Route::post('/design/reports/{reportTemplate}/publish', [ReportTemplateController::class, 'publish'])
                ->middleware('company.module:design_reports,write');

            Route::get('/design/workflows', [WorkflowDefinitionController::class, 'index'])
                ->middleware('company.module:design_workflows,read');
            Route::get('/design/workflows/{workflowDefinition}', [WorkflowDefinitionController::class, 'show'])
                ->middleware('company.module:design_workflows,read');
            Route::put('/design/workflows/{workflowDefinition}/definition', [WorkflowDefinitionController::class, 'updateDefinition'])
                ->middleware('company.module:design_workflows,write');

            Route::put('/routine-types/{routineType}/workflow', [WorkflowDefinitionController::class, 'updateRoutineTypeWorkflow'])
                ->middleware('company.module:design_workflows,write');

            Route::get('/routine-types', [RoutineTypeController::class, 'index'])
                ->middleware('company.module:design_routine_types,read');
            Route::put('/routine-types/{routineType}/design', [RoutineTypeController::class, 'updateDesign'])
                ->middleware('company.module:design_routine_types,write');

            Route::get('/routines', [RoutineController::class, 'index'])
                ->middleware('company.module:routines,read');
            Route::post('/routines', [RoutineController::class, 'store'])
                ->middleware('company.module:routines,write');
            Route::get('/routines/{routine}', [RoutineController::class, 'show'])
                ->middleware('company.module:routines,read');
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
