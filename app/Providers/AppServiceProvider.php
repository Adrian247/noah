<?php

namespace App\Providers;

use App\Events\RoutineValidated;
use App\Listeners\DispatchRoutineValidatedIntegrations;
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
        Event::listen(RoutineValidated::class, DispatchRoutineValidatedIntegrations::class);
    }
}
