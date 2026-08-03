<?php

namespace Tests\Feature\Api;

use App\Mail\WorkflowStepMail;
use App\Models\Asset;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\User;
use App\Services\Routines\DemoRoutineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class RoutineAssignmentEmailTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    public function test_creating_assigned_routine_queues_assignment_email(): void
    {
        $this->seed();
        Mail::fake();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        $company = $this->meinCompany();

        $token = $admin->createToken('test')->plainTextToken;
        $siteId = Site::query()->where('company_id', $company->id)->value('id');
        $assetId = Asset::query()->where('company_id', $company->id)->value('id');
        $typeId = RoutineType::query()
            ->where('company_id', $company->id)
            ->where('slug', 'revision-mayor-vehiculo-premium')
            ->value('id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/routines', [
                'site_id' => $siteId,
                'asset_id' => $assetId,
                'routine_type_id' => $typeId,
                'assigned_to' => $technician->id,
            ])
            ->assertCreated();

        Mail::assertQueued(WorkflowStepMail::class, function (WorkflowStepMail $mail) use ($technician) {
            return $mail->hasTo($technician->email)
                && str_contains($mail->mailSubject, 'asignada')
                && str_contains($mail->mailMessage, '<p>')
                && ! str_contains($mail->mailMessage, '&lt;p&gt;');
        });
    }

    public function test_demo_factory_also_queues_assignment_email(): void
    {
        $this->seed();
        Mail::fake();

        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        $company = $this->meinCompany();

        app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);

        Mail::assertQueued(WorkflowStepMail::class, fn (WorkflowStepMail $mail) => $mail->hasTo($technician->email));
    }
}
