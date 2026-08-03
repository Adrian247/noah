<?php

namespace Tests\Feature\Api;

use App\Models\FormDefinition;
use App\Models\FormOptionCatalog;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDemoRoutine;
use Tests\Support\UsesMeinCompany;
use Tests\Support\VehicleDemoFormResponses;
use Tests\TestCase;

class FormDesignApiTest extends TestCase
{
    use CreatesDemoRoutine;
    use RefreshDatabase;
    use UsesMeinCompany;

    private function adminHeaders(): array
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->first();
        $company = $this->meinCompany();

        return [
            'token' => $admin->createToken('test')->plainTextToken,
            'company_id' => (string) $company->id,
            'company' => $company,
        ];
    }

    public function test_admin_can_manage_option_catalogs(): void
    {
        $h = $this->adminHeaders();

        $create = $this->withToken($h['token'])
            ->withHeader('X-Company-Id', $h['company_id'])
            ->postJson('/api/v1/design/forms/option-catalogs', [
                'name' => 'Prioridades',
                'options' => [
                    ['value' => 'alta', 'label' => 'Alta'],
                    ['value' => 'baja', 'label' => 'Baja'],
                ],
            ])
            ->assertCreated();

        $id = $create->json('data.id');

        $this->withToken($h['token'])
            ->withHeader('X-Company-Id', $h['company_id'])
            ->getJson('/api/v1/design/forms/option-catalogs')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Prioridades']);

        $this->withToken($h['token'])
            ->withHeader('X-Company-Id', $h['company_id'])
            ->putJson("/api/v1/design/forms/option-catalogs/{$id}", [
                'name' => 'Prioridades actualizadas',
                'options' => [
                    ['value' => 'baja', 'label' => 'Baja'],
                    ['value' => 'alta', 'label' => 'Alta'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.options.0.value', 'baja')
            ->assertJsonPath('data.options.1.value', 'alta');
    }

    public function test_admin_can_update_form_image_settings(): void
    {
        $h = $this->adminHeaders();

        $this->withToken($h['token'])
            ->withHeader('X-Company-Id', $h['company_id'])
            ->putJson('/api/v1/design/forms/settings', [
                'max_image_size_kb' => 1024,
                'allowed_image_mimes' => ['image/jpeg', 'image/png'],
            ])
            ->assertOk()
            ->assertJsonPath('data.max_image_size_kb', 1024);

        $this->assertDatabaseHas('companies', [
            'id' => $h['company']->id,
            'form_max_image_size_kb' => 1024,
        ]);
    }

    public function test_form_show_includes_design_metadata(): void
    {
        $h = $this->adminHeaders();
        $form = FormDefinition::query()->where('company_id', $h['company']->id)->first();

        $this->withToken($h['token'])
            ->withHeader('X-Company-Id', $h['company_id'])
            ->getJson("/api/v1/design/forms/{$form->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'versions'],
                'form_design' => [
                    'settings' => ['max_image_size_kb', 'allowed_image_mimes'],
                    'option_catalogs',
                ],
            ]);
    }

    public function test_execution_rejects_invalid_select_value(): void
    {
        $this->seed();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        $company = $this->meinCompany();
        $routine = $this->demoRoutine();
        $token = $technician->createToken('test')->plainTextToken;

        $catalog = FormOptionCatalog::query()->where('company_id', $company->id)->first();
        $routine->load('routineType');
        $version = FormVersion::query()->find($routine->routineType?->form_version_id);
        $this->assertNotNull($version);
        $schema = $version->schema;
        $schema['sections'][0]['fields'][] = [
            'key' => 'estado_test',
            'type' => 'select',
            'label' => 'Estado test',
            'required' => true,
            'option_catalog_id' => $catalog->id,
        ];
        $version->update(['schema' => $schema]);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->postJson("/api/v1/routines/{$routine->id}/executions", [
                'technician_comments' => 'prueba validación',
                'duration_minutes' => 30,
                'responses' => array_merge(VehicleDemoFormResponses::required(), [
                    'estado_test' => 'valor-invalido',
                ]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado_test']);
    }

    public function test_form_field_upload_respects_size_policy(): void
    {
        Storage::fake('evidence');
        config(['phoenix.evidence.disk' => 'evidence']);

        $this->seed();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->first();
        $company = $this->meinCompany();
        $company->update(['form_max_image_size_kb' => 1]);
        $routine = $this->demoRoutine();
        $token = $technician->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('large.jpg', 50, 'image/jpeg');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->post("/api/v1/routines/{$routine->id}/form-field-upload", [
                'field_key' => 'foto_equipo',
                'file' => $file,
            ])
            ->assertStatus(422);
    }

    public function test_form_field_media_streams_demo_photo(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@pyro-systems.com')->firstOrFail();
        $company = $this->meinCompany();
        $routine = $this->demoRoutine();
        $token = $admin->createToken('test')->plainTextToken;

        $responses = $routine->latestExecution?->responses ?? [];
        $photoPath = null;
        foreach ($responses as $value) {
            if (! is_array($value)) {
                continue;
            }
            foreach ($value as $entry) {
                if (is_array($entry) && isset($entry['path'])) {
                    $photoPath = $entry['path'];
                    break 2;
                }
            }
        }
        $this->assertNotNull($photoPath);

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->get('/api/v1/routines/'.$routine->id.'/form-field-media?path='.urlencode((string) $photoPath))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->withToken($token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->get('/api/v1/routines/'.$routine->id.'/form-field-media?path='.urlencode('executions/999/demo/x.jpg'))
            ->assertForbidden();
    }
}
