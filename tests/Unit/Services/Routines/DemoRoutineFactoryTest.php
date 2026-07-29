<?php

namespace Tests\Unit\Services\Routines;

use App\Models\Company;
use App\Models\User;
use App\Services\Routines\DemoRoutineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRoutineFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_routine_responses_pass_form_validation(): void
    {
        $this->seed();

        $company = Company::query()->firstOrFail();
        $technician = User::query()->where('email', 'misael.palos@mein-company.com')->firstOrFail();

        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $responses = $routine->latestExecution?->responses ?? [];

        $this->assertSame('tres_cuartos', $responses['nivel_combustible'] ?? null);
        $this->assertNotEmpty($responses['kilometraje'] ?? null);
        $this->assertArrayHasKey('frenos', $responses);

        $disk = \Illuminate\Support\Facades\Storage::disk(config('phoenix.evidence.disk', 'evidence'));
        $photoPaths = [];
        foreach ($responses as $value) {
            if (! is_array($value)) {
                continue;
            }
            foreach ($value as $entry) {
                if (is_array($entry) && isset($entry['path']) && is_string($entry['path'])) {
                    $photoPaths[] = $entry['path'];
                }
            }
        }
        $this->assertNotEmpty($photoPaths, 'Demo routine should include at least one photo field.');
        foreach ($photoPaths as $photoPath) {
            $this->assertTrue($disk->exists($photoPath), 'Demo photos must be stored on the evidence disk for PDF reports.');
        }

        $frenos = $responses['foto_frenos'] ?? null;
        $this->assertIsArray($frenos);
        $this->assertCount(4, $frenos, 'foto_frenos debe incluir galería demo de 4 imágenes.');

        $neumaticos = $responses['foto_neumaticos'] ?? null;
        $this->assertIsArray($neumaticos);
        $this->assertCount(3, $neumaticos, 'foto_neumaticos debe incluir galería demo de 3 imágenes.');

        $pathsByField = [];
        foreach ($photoPaths as $photoPath) {
            $pathsByField[$photoPath] = true;
        }
        $this->assertCount(
            count($photoPaths),
            array_keys($pathsByField),
            'Cada foto demo debe tener ruta única.',
        );
    }
}
