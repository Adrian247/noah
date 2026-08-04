<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesDemoRoutine;
use Tests\TestCase;

class RoutineDestroyApiTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;

    public function test_administrator_can_delete_routine(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $companyId = (int) $admin->memberships()->where('is_active', true)->orderBy('company_id')->value('company_id');
        $routine = $this->demoRoutine();

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson('/api/v1/routines/'.$routine->id)
            ->assertNoContent();

        $this->assertNull(Routine::query()->withoutGlobalScopes()->find($routine->id));
    }

    public function test_technician_cannot_delete_routine(): void
    {
        $this->seed();

        $technician = User::query()->where('email', 'technician@sandbox-demo.com')->firstOrFail();
        $companyId = (int) $technician->memberships()->where('is_active', true)->orderBy('company_id')->value('company_id');
        $routine = $this->demoRoutine($technician);

        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson('/api/v1/routines/'.$routine->id)
            ->assertForbidden();
    }

    public function test_cannot_delete_routine_with_issued_invoice(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $companyId = (int) $admin->memberships()->where('is_active', true)->orderBy('company_id')->value('company_id');
        $routine = $this->demoRoutine();

        Invoice::query()->create([
            'company_id' => $companyId,
            'routine_id' => $routine->id,
            'status' => InvoiceStatus::Issued,
            'currency' => 'MXN',
            'subtotal' => 100,
            'tax_total' => 16,
            'total' => 116,
        ]);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->withHeader('X-Company-Id', (string) $companyId)
            ->deleteJson('/api/v1/routines/'.$routine->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar un servicio facturado.');

        $this->assertNotNull(Routine::query()->withoutGlobalScopes()->find($routine->id));
    }
}
