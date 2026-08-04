<?php

namespace Tests\Feature\Api;

use App\Enums\RoutineStatus;
use App\Models\SupplyItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDemoRoutine;
use Tests\Support\UsesMeinCompany;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class RoutineExecutionConsumptionsTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;
    use UsesMeinCompany;

    public function test_execution_persists_consumptions(): void
    {
        $this->seed();
        $company = $this->meinCompany();
        $user = $this->meinUser('technician@sandbox-demo.com');
        $routine = $this->demoRoutine($user);
        $supply = SupplyItem::query()->where('company_id', $company->id)->orderBy('id')->firstOrFail();
        $supply->update(['quantity_on_hand' => 50]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'cambio de filtro',
                'duration_minutes' => 45,
                'responses' => VehicleDemoFormResponses::required(),
                'consumptions' => [
                    ['supply_item_id' => $supply->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.consumptions.0.quantity', '2.0000');

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingValidation, $routine->status);
        $this->assertCount(1, $routine->latestExecution?->consumptions ?? []);
        $supply->refresh();
        $this->assertSame('48.0000', (string) $supply->quantity_on_hand);
    }
}
