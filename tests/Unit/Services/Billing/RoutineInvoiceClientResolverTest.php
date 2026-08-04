<?php

namespace Tests\Unit\Services\Billing;

use App\Models\AssetClientAssignment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Routine;
use App\Services\Billing\RoutineInvoiceClientResolver;
use App\Services\Routines\DemoRoutineFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineInvoiceClientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_client_from_active_asset_assignment(): void
    {
        $this->seed();
        $company = Company::query()->firstOrFail();
        $client = Client::query()->where('company_id', $company->id)->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $asset = $routine->asset;
        $this->assertNotNull($asset);

        AssetClientAssignment::query()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'client_id' => $client->id,
            'serial_number' => $asset->serial_number ?? 'SERIE-TEST',
            'assigned_at' => now(),
        ]);

        $resolved = app(RoutineInvoiceClientResolver::class)->resolveForRoutine($routine->fresh());

        $this->assertSame($client->id, $resolved);
    }
}
