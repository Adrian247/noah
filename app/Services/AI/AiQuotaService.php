<?php

namespace App\Services\AI;

use App\Models\AiInvocation;
use App\Models\Company;

class AiQuotaService
{
    public function assertVisionQuota(?int $companyId): void
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

    public function assertTokenQuota(?int $companyId, int $estimatedTokens = 0): void
    {
        if ($companyId === null) {
            return;
        }

        $company = Company::query()->find($companyId);
        $quota = $company?->ai_monthly_token_quota;
        if ($quota === null) {
            return;
        }

        $used = (int) AiInvocation::query()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(COALESCE(input_tokens,0) + COALESCE(output_tokens,0)), 0) as total')
            ->value('total');

        if (($used + $estimatedTokens) >= $quota) {
            throw new \RuntimeException('Cuota mensual de tokens IA alcanzada para esta empresa.');
        }
    }
}
