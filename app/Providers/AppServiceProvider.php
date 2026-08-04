<?php

namespace App\Providers;

use App\Services\AI\Tools\AiToolRegistry;
use App\Services\AI\Tools\GetClientDetailTool;
use App\Services\AI\Tools\GetEquipmentHealthTool;
use App\Services\AI\Tools\GetOperationalKpisTool;
use App\Services\AI\Tools\GetRoutineTool;
use App\Services\AI\Tools\ListAuditEntriesTool;
use App\Services\AI\Tools\ListCatalogItemsTool;
use App\Services\AI\Tools\ListClientsTool;
use App\Services\AI\Tools\ListFailureModesTool;
use App\Services\AI\Tools\ListInvoicesTool;
use App\Services\AI\Tools\ListRecentRoutinesTool;
use App\Services\AI\Tools\ListSitesTool;
use App\Services\AI\Tools\ListSupplyItemsTool;
use App\Services\AI\Tools\PredictClientDemandTool;
use App\Services\AI\Tools\PredictEquipmentFailuresTool;
use App\Services\AI\Tools\PredictInventoryDemandTool;
use App\Services\AI\Tools\SearchAssetsTool;
use App\Support\CurrentCompany;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class, fn () => new CurrentCompany);

        $this->app->singleton(AiToolRegistry::class, function ($app) {
            return new AiToolRegistry([
                new ListRecentRoutinesTool,
                new GetRoutineTool,
                new ListAuditEntriesTool,
                new SearchAssetsTool,
                new ListCatalogItemsTool,
                new ListSupplyItemsTool,
                new ListClientsTool,
                new GetClientDetailTool,
                new ListInvoicesTool,
                new ListSitesTool,
                new GetOperationalKpisTool,
                $app->make(PredictEquipmentFailuresTool::class),
                $app->make(PredictClientDemandTool::class),
                $app->make(PredictInventoryDemandTool::class),
                $app->make(GetEquipmentHealthTool::class),
                $app->make(ListFailureModesTool::class),
            ]);
        });
    }

    public function boot(): void
    {
        // Listeners de RoutineValidated: registro automático vía discovery (App\Listeners).
        // No volver a registrarlos aquí: duplicaría reportes PDF, borradores, etc.
    }
}
