<?php

namespace Tests\Feature\Api;

use App\Enums\RoutineStatus;
use App\Models\Company;
use App\Models\Routine;
use App\Models\SupplyItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineExecutionConsumptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_persists_consumptions(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'tecnico@noah.local')->first();
        $company = Company::query()->first();
        $routine = Routine::query()->first();
        $supply = SupplyItem::query()->first();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'cambio de filtro',
                'duration_minutes' => 45,
                'responses' => ['horometro' => 1200],
                'consumptions' => [
                    ['supply_item_id' => $supply->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.consumptions.0.quantity', '2.0000');

        $routine->refresh();
        $this->assertSame(RoutineStatus::PendingValidation, $routine->status);
        $this->assertCount(1, $routine->latestExecution?->consumptions ?? []);
    }
}
