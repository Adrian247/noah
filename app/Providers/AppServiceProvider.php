<?php

namespace App\Providers;

use App\Events\RoutineValidated;
use App\Listeners\CreateInvoiceDraft;
use App\Listeners\GenerateRoutineReport;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class, fn () => new CurrentCompany);
    }

    public function boot(): void
    {
        Event::listen(RoutineValidated::class, GenerateRoutineReport::class);
        Event::listen(RoutineValidated::class, CreateInvoiceDraft::class);
    }
}
