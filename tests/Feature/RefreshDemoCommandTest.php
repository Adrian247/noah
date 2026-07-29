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
        $this->artisan('phoenix:refresh-demo', ['--skip-migrate' => true])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'admin@pyro-systems.com')->exists());

        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $this->assertTrue(Hash::check(config('phoenix.demo_root_password'), $admin->password));
    }
}
