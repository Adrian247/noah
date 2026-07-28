<?php

namespace App\Support;

use App\Models\WorkflowInstance;

class AuditCorrelation
{
    private static ?string $current = null;

    public static function set(?string $correlationId): void
    {
        self::$current = $correlationId;
    }

    public static function get(): ?string
    {
        return self::$current;
    }

    public static function forWorkflowInstance(?WorkflowInstance $instance): ?string
    {
        if ($instance === null) {
            return null;
        }

        return $instance->correlation_id;
    }
}
