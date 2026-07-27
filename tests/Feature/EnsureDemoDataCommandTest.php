<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_seeds_when_no_users(): void
    {
        $this->assertSame(0, User::query()->count());

        $this->artisan('noah:ensure-demo')->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'admin@noah.local')->exists());
    }

    public function test_command_skips_full_seed_when_demo_admin_exists(): void
    {
        $this->seed();
        $before = User::query()->count();

        $this->artisan('noah:ensure-demo')->assertSuccessful();

        $this->assertSame($before, User::query()->count());
    }

    public function test_reset_credentials_restores_demo_password(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@noah.local')->firstOrFail();
        $admin->update(['password' => 'wrong-secret']);

        $this->artisan('noah:ensure-demo --reset-credentials')->assertSuccessful();

        $admin->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(config('noah.demo_password'), $admin->password));
    }

    public function test_reset_credentials_on_empty_database_seeds_demo(): void
    {
        $this->assertSame(0, User::query()->count());

        $this->artisan('noah:ensure-demo --reset-credentials')->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'admin@noah.local')->exists());
        $this->assertTrue(\App\Models\Company::query()->where('name', 'Demo Industrial')->exists());
    }
}
