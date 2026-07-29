<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Routine;
use App\Models\User;
use App\Services\Routines\DemoRoutineFactory;

trait CreatesDemoRoutine
{
    protected function demoRoutine(?User $technician = null): Routine
    {
        $company = Company::query()->firstOrFail();
        $technician ??= User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();

        return app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
    }

    /**
     * @return array<string, mixed>
     */
    protected function premiumFormResponses(): array
    {
        return VehicleDemoFormResponses::required();
    }
}
