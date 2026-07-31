<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use App\Services\Integrations\WebhookDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutomationRuleRunner
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function runTrigger(int $companyId, string $triggerType, array $context): void
    {
        $rules = AutomationRule::query()
            ->where('company_id', $companyId)
            ->where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matchesConditions($rule->conditions ?? [], $context)) {
                continue;
            }

            foreach ($rule->actions ?? [] as $action) {
                $this->runAction($companyId, $triggerType, $action, $context);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @param  array<string, mixed>  $context
     */
    private function matchesConditions(?array $conditions, array $context): bool
    {
        if ($conditions === null || $conditions === []) {
            return true;
        }

        foreach ($conditions as $key => $expected) {
            $actual = data_get($context, $key);
            if (is_array($expected) && isset($expected['min'])) {
                if (! is_numeric($actual) || (float) $actual < (float) $expected['min']) {
                    return false;
                }

                continue;
            }

            if ($actual != $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $context
     */
    private function runAction(int $companyId, string $triggerType, array $action, array $context): void
    {
        $type = (string) ($action['type'] ?? '');

        match ($type) {
            'webhook' => $this->webhooks->dispatch(
                $companyId,
                (string) ($action['event'] ?? $triggerType),
                array_merge($context, ['automation' => true]),
            ),
            'log' => Log::info('Automation rule', [
                'company_id' => $companyId,
                'trigger' => $triggerType,
                'message' => (string) ($action['message'] ?? 'Automation fired'),
                'context' => $context,
            ]),
            default => null,
        };
    }
}
