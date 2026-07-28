<?php

namespace App\Services\Workflow;

use App\Enums\MembershipRole;
use App\Mail\RoutinePendingValidationMail;
use App\Mail\WorkflowStepMail;
use App\Models\CompanyMembership;
use App\Models\Routine;
use App\Models\User;
use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowStepEmailNotifier
{
    /**
     * @param  array<string, mixed>  $stepMeta
     */
    public function sendForServiceTask(Routine $routine, array $stepMeta): void
    {
        $email = $stepMeta['email'] ?? null;
        if (! is_array($email)) {
            return;
        }
        $this->dispatchLegacy($routine, $email);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function sendFromConfig(Routine $routine, array $config): void
    {
        $this->dispatchLegacy($routine, $config);
    }

    /**
     * Correo configurado en una transición del workflow (acción).
     *
     * @param  array<string, mixed>  $notifyConfig
     */
    public function sendForTransitionNotify(Routine $routine, array $notifyConfig, User $actor): void
    {
        if (empty($notifyConfig['enabled'])) {
            return;
        }

        $recipients = $this->resolveTransitionRecipients($routine, $notifyConfig, $actor);
        if ($recipients->isEmpty()) {
            Log::warning('Workflow transition email: no recipients', [
                'routine_id' => $routine->id,
                'recipients' => $notifyConfig['recipients'] ?? [],
            ]);

            return;
        }

        foreach ($recipients as $user) {
            $this->queueToUser($routine, $notifyConfig, $user);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dispatchLegacy(Routine $routine, array $config): void
    {
        $roles = $config['roles'] ?? ['supervisor'];
        if (! is_array($roles)) {
            $roles = ['supervisor'];
        }

        $emails = $this->resolveEmailsByRoles($routine->company_id, $roles);
        if ($emails->isEmpty()) {
            Log::warning('Workflow email step: no recipients', [
                'routine_id' => $routine->id,
                'roles' => $roles,
            ]);

            return;
        }

        $template = (string) ($config['template'] ?? 'workflow_generic');
        $renderer = app(WorkflowEmailBodyRenderer::class);
        $subject = $renderer->subject($routine, $config);
        $message = $renderer->render($routine, $config);

        foreach ($emails as $email) {
            try {
                $mailable = match ($template) {
                    'routine_pending_validation' => new RoutinePendingValidationMail($routine->loadMissing(['asset', 'routineType'])),
                    default => new WorkflowStepMail($routine->loadMissing(['asset', 'routineType']), $subject, $message),
                };
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::error('Workflow email step failed', [
                    'email' => $email,
                    'routine_id' => $routine->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function queueToUser(Routine $routine, array $config, User $user): void
    {
        if ($user->email === null || $user->email === '') {
            return;
        }

        $renderer = app(WorkflowEmailBodyRenderer::class);
        $subject = $renderer->subject($routine, $config, $user);
        $message = $renderer->render($routine, $config, $user);

        try {
            Mail::to($user->email)->queue(
                new WorkflowStepMail($routine->loadMissing(['asset', 'routineType']), $subject, $message),
            );
        } catch (\Throwable $e) {
            Log::error('Workflow transition email failed', [
                'email' => $user->email,
                'routine_id' => $routine->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $notifyConfig
     * @return Collection<int, User>
     */
    private function resolveTransitionRecipients(Routine $routine, array $notifyConfig, User $actor): Collection
    {
        $keys = $notifyConfig['recipients'] ?? [];
        if (! is_array($keys)) {
            $keys = [];
        }

        $users = collect();
        $routine->loadMissing(['latestExecution.performer', 'assignee', 'creator', 'workflowInstance']);

        foreach ($keys as $key) {
            $users = $users->merge(match ($key) {
                'executing_technician' => $this->usersFromIds($this->technicianUserIds($routine)),
                'incident_creator' => $this->usersFromIds($this->incidentCreatorIds($routine)),
                'approval_supervisor' => $this->usersFromIds($this->approvalSupervisorIds($routine, $actor)),
                'roles' => $this->usersFromRoleList($routine->company_id, is_array($notifyConfig['roles'] ?? null) ? $notifyConfig['roles'] : []),
                default => collect(),
            });
        }

        return $users->unique('id')->values();
    }

    /**
     * @return list<int>
     */
    private function technicianUserIds(Routine $routine): array
    {
        $ids = [];
        $execution = $routine->latestExecution;
        if ($execution?->performed_by) {
            $ids[] = (int) $execution->performed_by;
        }
        if ($routine->assigned_to) {
            $ids[] = (int) $routine->assigned_to;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return list<int>
     */
    private function incidentCreatorIds(Routine $routine): array
    {
        if ($routine->created_by) {
            return [(int) $routine->created_by];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function approvalSupervisorIds(Routine $routine, User $fallbackActor): array
    {
        $execution = $routine->latestExecution;
        if ($execution?->validated_by) {
            return [(int) $execution->validated_by];
        }

        $instance = $routine->workflowInstance;
        if ($instance !== null) {
            $lastApproval = WorkflowTransition::query()
                ->where('workflow_instance_id', $instance->id)
                ->where('trigger', 'approved')
                ->where('from_step', WorkflowBlockCompiler::SUPERVISOR_STEP)
                ->orderByDesc('occurred_at')
                ->first();

            if ($lastApproval?->actor_user_id) {
                return [(int) $lastApproval->actor_user_id];
            }
        }

        return [$fallbackActor->id];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    private function usersFromIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->get();
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    private function usersFromRoleList(int $companyId, array $roles): Collection
    {
        $emails = $this->resolveEmailsByRoles($companyId, $roles);

        return User::query()->whereIn('email', $emails->all())->get();
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, string>
     */
    private function resolveEmailsByRoles(int $companyId, array $roles): Collection
    {
        $membershipRoles = [];
        foreach ($roles as $role) {
            $enum = MembershipRole::tryFrom($role);
            if ($enum !== null) {
                $membershipRoles[] = $enum;
            }
        }
        if ($membershipRoles === []) {
            $membershipRoles = [MembershipRole::Supervisor];
        }

        $userIds = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('role', $membershipRoles)
            ->pluck('user_id');

        return User::query()->whereIn('id', $userIds)->pluck('email')->unique()->values();
    }
}
