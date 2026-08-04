<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoutineDestroyApiTest extends TestCase
{
    use RefreshDatabase;

    private function createRoutineViaApi(string $adminToken, int $companyId): int
    {
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();

        $response = $this->withToken($adminToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->postJson('/api/v1/routines', [
                'site_id' => \App\Models\Site::query()->where('company_id', $companyId)->value('id'),
                'asset_id' => \App\Models\Asset::query()->where('company_id', $companyId)->value('id'),
                'routine_type_id' => \App\Models\RoutineType::query()->where('company_id', $companyId)->value('id'),
                'assigned_to' => $technician->id,
            ])
            ->assertCreated();

        return (int) $response->json('data.id');
    }

    public function test_administrator_can_delete_routine(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;
        $routineId = $this->createRoutineViaApi($token, $company->id);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson('/api/v1/routines/'.$routineId)
            ->assertNoContent();

        $this->assertNull(Routine::query()->withoutGlobalScopes()->find($routineId));
    }

    public function test_technician_cannot_delete_routine(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $adminToken = $admin->createToken('test')->plainTextToken;
        $routineId = $this->createRoutineViaApi($adminToken, $company->id);

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson('/api/v1/routines/'.$routineId)
            ->assertForbidden();
    }

    public function test_cannot_delete_routine_with_issued_invoice(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;
        $routineId = $this->createRoutineViaApi($token, $company->id);

        Invoice::query()->create([
            'company_id' => $company->id,
            'routine_id' => $routineId,
            'status' => InvoiceStatus::Issued,
            'currency' => 'MXN',
            'subtotal' => 100,
            'tax_total' => 16,
            'total' => 116,
        ]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson('/api/v1/routines/'.$routineId)
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar: el servicio tiene una factura emitida.');

        $this->assertNotNull(Routine::query()->withoutGlobalScopes()->find($routineId));
    }
}
