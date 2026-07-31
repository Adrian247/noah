<?php

namespace App\Services\AI;

use App\Models\AiInvocation;
use App\Models\Company;
use App\Models\Routine;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiCapabilitiesService
{
    public function generateReportNarrative(Routine $routine, ?int $userId = null): string
    {
        $routine->loadMissing(['asset.catalogItem', 'routineType', 'latestExecution', 'company']);
        $execution = $routine->latestExecution;
        $responses = is_array($execution?->responses) ? $execution->responses : [];

        $bullets = [];
        foreach ($responses as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $bullets[] = sprintf('%s: %s', (string) $key, (string) ($value ?? '—'));
            }
        }

        $draft = sprintf(
            "Se completó la rutina #%d (%s) en el activo %s.\n\nHallazgos registrados:\n- %s\n\n%s",
            $routine->id,
            $routine->routineType?->name ?? 'Servicio',
            $routine->asset?->tag ?? '—',
            $bullets === [] ? 'Sin campos adicionales.' : implode("\n- ", $bullets),
            $execution?->technician_comments ? 'Comentario del técnico: '.$execution->technician_comments : '',
        );

        return $this->logAndReturn($routine->company_id, $userId, 'report_narrative', $draft);
    }

    public function extractPlateText(string $imageContents, ?int $userId = null): string
    {
        $companyId = app(CurrentCompany::class)->id();
        $this->assertVisionQuota($companyId);

        $provider = config('phoenix.ai.default_provider', 'local');
        if ($provider === 'openai' && config('phoenix.ai.openai.api_key')) {
            try {
                $base64 = base64_encode($imageContents);
                $response = Http::withToken(config('phoenix.ai.openai.api_key'))
                    ->timeout(45)
                    ->post(rtrim(config('phoenix.ai.openai.base_url'), '/').'/chat/completions', [
                        'model' => config('phoenix.ai.openai.vision_model', 'gpt-4o-mini'),
                        'messages' => [[
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'Extrae texto visible de placa o etiqueta. Solo el texto, sin explicación.'],
                                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$base64]],
                            ],
                        ]],
                    ]);

                if ($response->successful()) {
                    $text = trim((string) $response->json('choices.0.message.content', ''));

                    return $this->logAndReturn($companyId, $userId, 'vision_ocr', $text !== '' ? $text : '—');
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->logAndReturn($companyId, $userId, 'vision_ocr', 'OCR no disponible en modo local (configure OpenAI para visión).');
    }

    private function assertVisionQuota(?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $company = Company::query()->find($companyId);
        $quota = $company?->ai_monthly_vision_quota;
        if ($quota === null) {
            return;
        }

        $used = AiInvocation::query()
            ->where('company_id', $companyId)
            ->where('use_case', 'vision_ocr')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($used >= $quota) {
            throw new \RuntimeException('Cuota mensual de visión IA alcanzada para esta empresa.');
        }
    }

    private function logAndReturn(?int $companyId, ?int $userId, string $useCase, string $output): string
    {
        if ($companyId !== null) {
            AiInvocation::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'use_case' => $useCase,
                'provider' => config('phoenix.ai.default_provider', 'local'),
                'model' => null,
                'input_excerpt' => Str::limit($useCase, 120),
                'output_excerpt' => Str::limit($output, 500),
                'status' => 'success',
            ]);
        }

        return $output;
    }
}
