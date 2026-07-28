<?php

namespace App\Services\Billing;

use App\Models\AssetClientAssignment;
use App\Models\Routine;

class RoutineInvoiceClientResolver
{
    public function resolveForRoutine(Routine $routine): ?int
    {
        $routine->loadMissing('asset');
        $assetId = $routine->asset_id;
        if ($assetId === null) {
            return null;
        }

        $assignment = AssetClientAssignment::query()
            ->where('asset_id', $assetId)
            ->whereNull('unassigned_at')
            ->orderByDesc('assigned_at')
            ->first();

        return $assignment?->client_id;
    }
}
