<?php

namespace App\Services\Mobile;

use App\Enums\RoutineStatus;
use App\Models\Routine;
use App\Models\RoutineType;
use App\Models\SyncEvent;
use App\Models\User;
use App\Services\Workflow\WorkflowRuntime;
use App\Support\CurrentCompany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MobileSyncService
{
    public function __construct(private WorkflowRuntime $workflow) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array{accepted: list<string>, rejected: list<array{event_id: string, reason: string}>}
     */
    public function pushEvents(User $user, string $deviceId, array $events): array
    {
        $company = app(CurrentCompany::class)->company;
        if ($company === null) {
            throw new InvalidArgumentException('Company context required.');
        }

        $accepted = [];
        $rejected = [];

        foreach ($events as $event) {
            $eventId = (string) Arr::get($event, 'event_id', '');
            $eventType = (string) Arr::get($event, 'event_type', '');
            $payload = Arr::get($event, 'payload', []);

            if ($eventId === '' || $eventType === '') {
                $rejected[] = ['event_id' => $eventId ?: 'unknown', 'reason' => 'event_id and event_type required'];

                continue;
            }

            $exists = SyncEvent::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('device_id', $deviceId)
                ->where('event_id', $eventId)
                ->exists();

            if ($exists) {
                $accepted[] = $eventId;

                continue;
            }

            try {
                DB::transaction(function () use ($company, $user, $deviceId, $eventId, $eventType, $payload): void {
                    $this->processEvent($user, $eventType, $payload);

                    SyncEvent::query()->create([
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'device_id' => $deviceId,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                        'payload' => $payload,
                        'processed_at' => now(),
                    ]);
                });
                $accepted[] = $eventId;
            } catch (\Throwable $e) {
                $rejected[] = ['event_id' => $eventId, 'reason' => $e->getMessage()];
            }
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * @return array<string, mixed>
     */
    public function pullForUser(User $user): array
    {
        $routines = Routine::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['assigned', 'pending_validation'])
            ->with([
                'asset.catalogItem',
                'site',
                'routineType.formVersion',
                'latestExecution',
            ])
            ->orderByDesc('id')
            ->get();

        $routineTypes = RoutineType::query()
            ->where('is_active', true)
            ->with(['formVersion', 'reportTemplateVersion'])
            ->get();

        return [
            'server_time' => now()->toIso8601String(),
            'routines' => $routines,
            'routine_types' => $routineTypes,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processEvent(User $user, string $eventType, array $payload): void
    {
        if ($eventType === 'execution.submitted') {
            $routineId = (int) Arr::get($payload, 'routine_id', 0);
            $routine = Routine::query()->findOrFail($routineId);

            if ($routine->assigned_to !== null && $routine->assigned_to !== $user->id) {
                throw new InvalidArgumentException('Routine not assigned to this user.');
            }

            if ($routine->status !== RoutineStatus::Assigned) {
                throw new InvalidArgumentException('Routine is not in assigned status.');
            }

            $execution = $routine->executions()->create([
                'performed_by' => $user->id,
                'responses' => Arr::get($payload, 'responses', []),
                'technician_comments' => Arr::get($payload, 'technician_comments'),
                'duration_minutes' => Arr::get($payload, 'duration_minutes'),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach (Arr::get($payload, 'consumptions', []) as $line) {
                $execution->consumptions()->create([
                    'supply_item_id' => $line['supply_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'] ?? 0,
                ]);
            }

            $this->workflow->onExecutionSubmitted($routine, $user);

            return;
        }

        throw new InvalidArgumentException("Unsupported event_type: {$eventType}");
    }
}
