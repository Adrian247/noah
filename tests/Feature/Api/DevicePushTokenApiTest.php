<?php

namespace Tests\Feature\Api;

use App\Jobs\SendPushNotificationJob;
use App\Models\Asset;
use App\Models\DevicePushToken;
use App\Models\RoutineType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class DevicePushTokenApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    public function test_technician_can_register_and_unregister_device_token(): void
    {
        $this->seed();
        $company = $this->meinCompany();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        Sanctum::actingAs($technician);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/mobile/device-tokens', [
                'device_id' => 'phoenix-test-device-1',
                'token' => 'fcm-token-abc-123',
                'platform' => 'android',
                'app_version' => '0.8.0',
            ])
            ->assertCreated()
            ->assertJsonPath('data.device_id', 'phoenix-test-device-1');

        $this->assertDatabaseHas('device_push_tokens', [
            'user_id' => $technician->id,
            'device_id' => 'phoenix-test-device-1',
            'token' => 'fcm-token-abc-123',
        ]);

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->deleteJson('/api/v1/mobile/device-tokens', [
                'device_id' => 'phoenix-test-device-1',
            ])
            ->assertNoContent();

        $this->assertDatabaseMissing('device_push_tokens', [
            'device_id' => 'phoenix-test-device-1',
        ]);
    }

    public function test_assigning_routine_dispatches_push_job_for_technician(): void
    {
        $this->seed();
        Queue::fake();

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();
        $company = $this->meinCompany();

        DevicePushToken::query()->create([
            'user_id' => $technician->id,
            'company_id' => $company->id,
            'device_id' => 'phoenix-test-device-2',
            'platform' => 'android',
            'token' => 'fcm-token-for-push',
            'last_seen_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $siteId = Site::query()->where('company_id', $company->id)->value('id');
        $assetId = Asset::query()->where('company_id', $company->id)->value('id');
        $typeId = RoutineType::query()
            ->where('company_id', $company->id)
            ->where('slug', 'revision-mayor-vehiculo-premium')
            ->value('id');

        $this->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/routines', [
                'site_id' => $siteId,
                'asset_id' => $assetId,
                'routine_type_id' => $typeId,
                'assigned_to' => $technician->id,
            ])
            ->assertCreated();

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($technician) {
            return in_array($technician->id, $job->userIds, true)
                && str_contains(mb_strtolower($job->title), 'asignada');
        });
    }
}
