<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAvatarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $this->seed();
        $user = User::query()->where('email', 'admin@noah.local')->first();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('avatar.png', 100, 'image/png');

        $response = $this->postJson('/api/v1/auth/avatar', ['avatar' => $file]);

        $response->assertOk()->assertJsonStructure(['user' => ['avatar_url']]);
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
    }
}
