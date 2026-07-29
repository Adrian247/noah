<?php

namespace Tests\Feature\Api;

use App\Enums\FormUsage;
use App\Models\Company;
use App\Models\EquipmentType;
use App\Models\FormDefinition;
use App\Models\FormVersion;
use App\Models\RoutineType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDefinitionUsageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_requires_usage_and_index_can_filter(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/design/forms', ['name' => 'Ficha equipo'])
            ->assertStatus(422);

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/design/forms', ['name' => 'Ficha equipo', 'usage' => 'equipment'])
            ->assertCreated()
            ->assertJsonPath('data.usage', 'equipment');

        $this->withToken($token)
            ->withHeaders($headers)
            ->getJson('/api/v1/design/forms?usage=equipment')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ficha equipo']);
    }

    public function test_delete_form_blocked_when_linked_to_equipment_type(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $form = FormDefinition::query()->create([
            'company_id' => $company->id,
            'name' => 'Temporal',
            'slug' => 'temporal-delete',
            'usage' => FormUsage::Equipment,
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

    public function test_equipment_type_rejects_routine_form(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $routineForm = FormDefinition::query()
            ->where('company_id', $company->id)
            ->where('usage', FormUsage::Routine)
            ->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson('/api/v1/catalog/equipment-types', [
                'code' => 'x-invalid-form',
                'name' => 'X',
                'default_form_definition_id' => $routineForm->id,
            ])
            ->assertStatus(422);
    }

    public function test_routine_type_rejects_equipment_form_version(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = Company::query()->first();
        $headers = ['X-Company-Id' => (string) $company->id];
        $token = $admin->createToken('test')->plainTextToken;

        $equipmentVersion = FormVersion::query()
            ->whereHas('definition', fn ($q) => $q->where('company_id', $company->id)->where('usage', FormUsage::Equipment))
            ->where('status', 'published')
            ->firstOrFail();

        $routineType = RoutineType::query()->where('company_id', $company->id)->firstOrFail();

        $this->withToken($token)
            ->withHeaders($headers)
            ->putJson('/api/v1/routine-types/'.$routineType->id.'/design', [
                'form_version_id' => $equipmentVersion->id,
            ])
            ->assertStatus(422);
    }
}
