<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoutineStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Routine;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $pendingValidation = Routine::query()
            ->where('status', RoutineStatus::PendingValidation)
            ->count();

        $assigned = Routine::query()
            ->where('status', RoutineStatus::Assigned)
            ->count();

        $validated = Routine::query()
            ->where('status', RoutineStatus::Validated)
            ->count();

        $draftInvoices = Invoice::query()
            ->where('status', 'draft')
            ->count();

        return response()->json([
            'data' => [
                'routines_pending_validation' => $pendingValidation,
                'routines_assigned' => $assigned,
                'routines_validated' => $validated,
                'invoices_draft' => $draftInvoices,
            ],
        ]);
    }
}
