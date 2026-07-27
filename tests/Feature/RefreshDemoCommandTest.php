<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RefreshDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_demo_seeds_and_resets_credentials(): void
    {
        $this->artisan('noah:refresh-demo', ['--skip-migrate' => true])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'admin@noah.local')->exists());

        $admin = User::query()->where('email', 'admin@noah.local')->firstOrFail();
        $this->assertTrue(Hash::check(config('noah.demo_password'), $admin->password));
    }
}
