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

    public function test_command_skips_when_users_exist(): void
    {
        $this->seed();
        $before = User::query()->count();

        $this->artisan('noah:ensure-demo')->assertSuccessful();

        $this->assertSame($before, User::query()->count());
    }
}
