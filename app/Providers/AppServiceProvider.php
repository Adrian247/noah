<?php

namespace App\Providers;

use App\Events\RoutineValidated;
use App\Listeners\DispatchRoutineValidatedIntegrations;
use App\Services\AI\Tools\AiToolRegistry;
use App\Services\AI\Tools\GetOperationalKpisTool;
use App\Services\AI\Tools\GetRoutineTool;
use App\Services\AI\Tools\ListAuditEntriesTool;
use App\Services\AI\Tools\ListClientsTool;
use App\Services\AI\Tools\ListInvoicesTool;
use App\Services\AI\Tools\ListRecentRoutinesTool;
use App\Services\AI\Tools\ListSitesTool;
use App\Services\AI\Tools\ListSupplyItemsTool;
use App\Services\AI\Tools\SearchAssetsTool;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class, fn () => new CurrentCompany);

        $this->app->singleton(AiToolRegistry::class, function () {
            return new AiToolRegistry([
                new ListRecentRoutinesTool,
                new GetRoutineTool,
                new ListAuditEntriesTool,
                new SearchAssetsTool,
                new ListSupplyItemsTool,
                new ListClientsTool,
                new ListInvoicesTool,
                new ListSitesTool,
                new GetOperationalKpisTool,
            ]);
        });
    }

    public function boot(): void
    {
        Event::listen(RoutineValidated::class, DispatchRoutineValidatedIntegrations::class);
    }
}
