<?php

namespace App\Listeners;

use App\Enums\MembershipRole;
use App\Events\ExecutionSubmitted;
use App\Mail\RoutinePendingValidationMail;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Services\Notifications\PushNotifier;
use App\Services\Workflow\WorkflowDefinitionFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifySupervisorsOfPendingValidation
{
    public function __construct(
        private readonly PushNotifier $push,
    ) {}

    public function handle(ExecutionSubmitted $event): void
    {
        $routine = $event->routine;
        $routine->loadMissing('workflowInstance.definition');
        $definition = $routine->workflowInstance?->definition?->definition;
        if (WorkflowDefinitionFactory::hasEmailServiceStep($definition)) {
            return;
        }

        $companyId = $routine->company_id;

        $supervisorIds = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('role', [MembershipRole::Supervisor, MembershipRole::Administrator])
            ->pluck('user_id');

        $users = User::query()->whereIn('id', $supervisorIds)->get();
        $emails = $users->pluck('email')->filter()->unique();

        if ($emails->isEmpty()) {
            Log::warning('ExecutionSubmitted: no supervisor/admin emails for company', [
                'company_id' => $companyId,
                'routine_id' => $routine->id,
            ]);

            return;
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new RoutinePendingValidationMail($routine));
            } catch (\Throwable $e) {
                Log::error('ExecutionSubmitted: failed to send pending validation mail', [
                    'email' => $email,
                    'routine_id' => $routine->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->push->notifyUsers(
            $users->pluck('id')->all(),
            'Rutina pendiente de validación',
            'La rutina #'.$routine->id.' espera revisión del supervisor.',
            [
                'type' => 'routine_pending_validation',
                'routine_id' => (string) $routine->id,
            ],
        );
    }
}
