<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDesignerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_publish_form(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $token = $admin->createToken('test')->plainTextToken;

        $create = $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/forms', ['name' => 'Checklist prueba', 'usage' => 'routine'])
            ->assertCreated();

        $formId = $create->json('data.id');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/forms/{$formId}/schema", [
                'schema' => [
                    'sections' => [
                        ['title' => 'A', 'fields' => [['key' => 'a', 'type' => 'text', 'label' => 'A']]],
                    ],
                ],
            ])
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/forms/{$formId}/publish")
            ->assertOk();

        $this->assertDatabaseHas('form_versions', [
            'form_definition_id' => $formId,
            'status' => 'published',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('form_versions', [
            'form_definition_id' => $formId,
            'status' => 'draft',
            'version' => 2,
        ]);

        // Segunda publicación: la v1 queda archivada; solo v2 publicada + v3 borrador.
        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->putJson("/api/v1/design/forms/{$formId}/schema", [
                'schema' => [
                    'sections' => [
                        ['title' => 'B', 'fields' => [['key' => 'b', 'type' => 'text', 'label' => 'B']]],
                    ],
                ],
            ])
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/design/forms/{$formId}/publish")
            ->assertOk();

        $this->assertDatabaseHas('form_versions', [
            'form_definition_id' => $formId,
            'status' => 'archived',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('form_versions', [
            'form_definition_id' => $formId,
            'status' => 'published',
            'version' => 2,
        ]);
        $this->assertDatabaseHas('form_versions', [
            'form_definition_id' => $formId,
            'status' => 'draft',
            'version' => 3,
        ]);
        $this->assertSame(
            1,
            \App\Models\FormVersion::query()
                ->where('form_definition_id', $formId)
                ->where('status', 'published')
                ->count(),
        );
    }

    public function test_technician_cannot_create_form(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'technician@sandbox-demo.com')->first();
        $company = Company::query()->first();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson('/api/v1/design/forms', ['name' => 'No permitido'])
            ->assertForbidden();
    }
}
