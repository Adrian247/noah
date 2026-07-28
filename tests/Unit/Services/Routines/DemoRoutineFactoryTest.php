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
        $technician = User::query()->where('email', 'tecnico@noah.local')->firstOrFail();

        $routine = app(DemoRoutineFactory::class)->createForCompany($company->id, $technician);
        $responses = $routine->latestExecution?->responses ?? [];

        $this->assertSame('tres_cuartos', $responses['nivel_combustible'] ?? null);
        $this->assertSame('si', $responses['filtro_aceite_reemplazado'] ?? null);
        $this->assertSame('operativo', $responses['motor_estado'] ?? null);

        $disk = \Illuminate\Support\Facades\Storage::disk(config('noah.evidence.disk', 'evidence'));
        $evidencia = $responses['foto_evidencia'] ?? [];
        $this->assertIsArray($evidencia);
        $this->assertNotEmpty($evidencia);
        $photoPath = $evidencia[0]['path'] ?? '';
        $this->assertNotSame('', $photoPath);
        $this->assertTrue($disk->exists($photoPath), 'Demo photos must be stored on the evidence disk for PDF reports.');
    }
}
