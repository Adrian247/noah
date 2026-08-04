<?php

namespace Tests\Feature\Api;

use App\Enums\FormUsage;
use App\Models\EquipmentType;
use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\RoutineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesMeinCompany;
use Tests\TestCase;

class FormDefinitionUsageApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesMeinCompany;

    public function test_create_form_requires_usage_and_index_can_filter(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = $this->meinCompany();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/design/forms', ['name' => 'Ficha artículo'])
            ->assertStatus(422);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/design/forms', ['name' => 'Ficha artículo', 'usage' => 'article'])
            ->assertCreated()
            ->assertJsonPath('data.usage', 'article');

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/design/forms?usage=article')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ficha artículo']);
    }

    public function test_index_legacy_usage_routine_resolves_to_service(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = $this->meinCompany();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $serviceForm = FormDefinition::query()
            ->where('company_id', $company->id)
            ->where('usage', FormUsage::Service)
            ->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/design/forms?usage=routine')
            ->assertOk()
            ->assertJsonFragment(['id' => $serviceForm->id, 'usage' => 'service']);

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/design/forms?usage=service')
            ->assertOk()
            ->assertJsonFragment(['id' => $serviceForm->id]);
    }

    public function test_delete_form_blocked_when_linked_to_equipment_type(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = $this->meinCompany();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $form = FormDefinition::query()->create([
            'company_id' => $company->id,
            'name' => 'Temporal',
            'slug' => 'temporal-delete',
            'usage' => FormUsage::Article,
        ]);

        FormVersion::query()->create([
            'form_definition_id' => $form->id,
            'version' => 1,
            'status' => 'draft',
            'schema' => ['sections' => []],
            'created_by' => $admin->id,
        ]);

        EquipmentType::query()->create([
            'company_id' => $company->id,
            'code' => 'test-tipo',
            'name' => 'Test',
            'default_form_definition_id' => $form->id,
        ]);

        $this->withToken($token)
            ->withHeaders($headers)
            ->deleteJson('/api/v1/design/forms/'.$form->id)
            ->assertStatus(422);

        EquipmentType::query()->where('code', 'test-tipo')->delete();

        $this->withToken($token)
            ->withHeaders($headers)
            ->deleteJson('/api/v1/design/forms/'.$form->id)
            ->assertNoContent();
    }

    public function test_equipment_type_rejects_service_form(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = $this->meinCompany();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $serviceForm = FormDefinition::query()
            ->where('company_id', $company->id)
            ->where('usage', FormUsage::Service)
            ->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/catalog/equipment-types', [
                'code' => 'x-invalid-form',
                'name' => 'X',
                'default_form_definition_id' => $serviceForm->id,
            ])
            ->assertStatus(422);
    }

    public function test_routine_type_rejects_article_form_version(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@sandbox-demo.com')->firstOrFail();
        $company = $this->meinCompany();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $articleVersion = FormVersion::query()
            ->whereHas('definition', fn ($q) => $q->where('company_id', $company->id)->where('usage', FormUsage::Article))
            ->where('status', 'published')
            ->firstOrFail();

        $routineType = RoutineType::query()->where('company_id', $company->id)->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->putJson('/api/v1/routine-types/'.$routineType->id.'/design', [
                'form_version_id' => $articleVersion->id,
            ])
            ->assertStatus(422);
    }
}
