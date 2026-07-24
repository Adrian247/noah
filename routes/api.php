<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogItemController;
use App\Http\Controllers\Api\V1\FormDefinitionController;
use App\Http\Controllers\Api\V1\GeneratedReportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\RoutineExecutionController;
use App\Http\Controllers\Api\V1\RoutineTypeController;
use App\Http\Controllers\Api\V1\ReportTemplateController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\SupplyItemController;
use App\Http\Controllers\Api\V1\WorkflowDefinitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'message' => 'Noah API',
        'product' => 'noah',
    ]));

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::middleware('company')->group(function (): void {
            Route::get('/sites', [SiteController::class, 'index']);

            Route::get('/catalog/items', [CatalogItemController::class, 'index']);
            Route::post('/catalog/items', [CatalogItemController::class, 'store']);
            Route::put('/catalog/items/{catalogItem}', [CatalogItemController::class, 'update']);
            Route::delete('/catalog/items/{catalogItem}', [CatalogItemController::class, 'destroy']);

            Route::get('/inventory/supplies', [SupplyItemController::class, 'index']);
            Route::post('/inventory/supplies', [SupplyItemController::class, 'store']);
            Route::put('/inventory/supplies/{supplyItem}', [SupplyItemController::class, 'update']);
            Route::delete('/inventory/supplies/{supplyItem}', [SupplyItemController::class, 'destroy']);

            Route::get('/assets', [AssetController::class, 'index']);
            Route::post('/assets', [AssetController::class, 'store']);
            Route::put('/assets/{asset}', [AssetController::class, 'update']);
            Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);

            Route::get('/design/forms', [FormDefinitionController::class, 'index']);
            Route::post('/design/forms', [FormDefinitionController::class, 'store']);
            Route::get('/design/forms/{form}', [FormDefinitionController::class, 'show']);
            Route::put('/design/forms/{form}/schema', [FormDefinitionController::class, 'updateSchema']);
            Route::post('/design/forms/{form}/publish', [FormDefinitionController::class, 'publish']);

            Route::get('/design/reports', [ReportTemplateController::class, 'index']);
            Route::post('/design/reports', [ReportTemplateController::class, 'store']);
            Route::get('/design/reports/{reportTemplate}', [ReportTemplateController::class, 'show']);
            Route::put('/design/reports/{reportTemplate}/components', [ReportTemplateController::class, 'updateComponents']);
            Route::post('/design/reports/{reportTemplate}/publish', [ReportTemplateController::class, 'publish']);

            Route::get('/design/workflows', [WorkflowDefinitionController::class, 'index']);
            Route::get('/design/workflows/{workflowDefinition}', [WorkflowDefinitionController::class, 'show']);
            Route::put('/design/workflows/{workflowDefinition}/definition', [WorkflowDefinitionController::class, 'updateDefinition']);

            Route::put('/routine-types/{routineType}/workflow', [WorkflowDefinitionController::class, 'updateRoutineTypeWorkflow']);

            Route::get('/routine-types', [RoutineTypeController::class, 'index']);

            Route::get('/routines', [RoutineController::class, 'index']);
            Route::post('/routines', [RoutineController::class, 'store']);
            Route::get('/routines/{routine}', [RoutineController::class, 'show']);
            Route::post('/routines/{routine}/executions', [RoutineExecutionController::class, 'store']);
            Route::post('/routines/{routine}/validate', [RoutineExecutionController::class, 'validateExecution']);
            Route::post('/routines/{routine}/reject', [RoutineExecutionController::class, 'reject']);

            Route::get('/routines/{routineId}/reports', [GeneratedReportController::class, 'index']);
            Route::get('/reports/{report}/download', [GeneratedReportController::class, 'download']);

            Route::get('/billing/invoices', [InvoiceController::class, 'index']);
            Route::get('/billing/invoices/{invoice}', [InvoiceController::class, 'show']);
            Route::post('/billing/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);

            Route::get('/audit/entries', [AuditController::class, 'index']);
        });
    });
});
