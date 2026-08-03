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
        $technician ??= User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();

        // La empresa sale de la membresía del técnico: con `Company::first()` la rutina caía en una
        // empresa arbitraria (Postgres no garantiza orden sin `ORDER BY`) y las peticiones que
        // mandan `X-Company-Id` respondían 403 o 404 de forma intermitente.
        $companyId = $technician->memberships()
            ->where('is_active', true)
            ->orderBy('company_id')
            ->value('company_id');

        $companyId ??= Company::query()->orderBy('id')->firstOrFail()->id;

        return app(DemoRoutineFactory::class)->createForCompany((int) $companyId, $technician);
    }

    /**
     * @return array<string, mixed>
     */
    protected function premiumFormResponses(): array
    {
        return VehicleDemoFormResponses::required();
    }
}
