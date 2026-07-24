<?php

namespace App\Listeners;

use App\Enums\MembershipRole;
use App\Events\ExecutionSubmitted;
use App\Mail\RoutinePendingValidationMail;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotifySupervisorsOfPendingValidation
{
    public function handle(ExecutionSubmitted $event): void
    {
        $routine = $event->routine;
        $companyId = $routine->company_id;

        $supervisorIds = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('role', [MembershipRole::Supervisor, MembershipRole::Administrator])
            ->pluck('user_id');

        $emails = User::query()->whereIn('id', $supervisorIds)->pluck('email')->unique();

        foreach ($emails as $email) {
            Mail::to($email)->send(new RoutinePendingValidationMail($routine));
        }
    }
}
