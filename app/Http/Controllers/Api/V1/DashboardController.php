<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoutineStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditEntry;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\FormDefinition;
use App\Models\Invoice;
use App\Models\ReportTemplate;
use App\Models\Routine;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SupplyItem;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\RoutineType;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $companyId = app(CurrentCompany::class)->id();

        $statusCounts = $this->routineCountsByStatus();

        $pendingValidation = $statusCounts[RoutineStatus::PendingValidation->value] ?? 0;
        $assigned = $statusCounts[RoutineStatus::Assigned->value] ?? 0;
        $validated = $statusCounts[RoutineStatus::Validated->value] ?? 0;
        $inProgress = $statusCounts[RoutineStatus::InProgress->value] ?? 0;
        $submitted = $statusCounts[RoutineStatus::Submitted->value] ?? 0;
        $pendingBilling = $statusCounts[RoutineStatus::PendingBilling->value] ?? 0;
        $rejected = $statusCounts[RoutineStatus::Rejected->value] ?? 0;

        $draftInvoices = Invoice::query()
            ->where('status', 'draft')
            ->count();

        $workflowsActive = WorkflowInstance::query()
            ->whereNull('completed_at')
            ->whereHas('routine', fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $lowStockQuery = SupplyItem::query()
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereColumn('quantity_on_hand', '<', 'min_stock');

        $lowStockCount = (clone $lowStockQuery)->count();

        $lowStockItems = (clone $lowStockQuery)
            ->orderBy('quantity_on_hand')
            ->limit(5)
            ->get(['id', 'name', 'sku', 'quantity_on_hand', 'min_stock', 'unit'])
            ->map(fn (SupplyItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity_on_hand' => (float) $item->quantity_on_hand,
                'min_stock' => (float) $item->min_stock,
                'unit' => $item->unit,
            ])
            ->values();

        $focusRoutines = Routine::query()
            ->with([
                'asset:id,tag',
                'site:id,name',
                'routineType:id,name',
            ])
            ->whereIn('status', [
                RoutineStatus::PendingValidation,
                RoutineStatus::Assigned,
                RoutineStatus::InProgress,
                RoutineStatus::Submitted,
            ])
            ->orderByRaw($this->focusRoutineOrderSql())
            ->orderByDesc('scheduled_at')
            ->limit(6)
            ->get()
            ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'status' => $routine->status->value,
                'scheduled_at' => $routine->scheduled_at?->toIso8601String(),
                'asset_tag' => $routine->asset?->tag,
                'site_name' => $routine->site?->name,
                'routine_type_name' => $routine->routineType?->name,
            ])
            ->values();

        $recentActivity = collect();
        if ($companyId !== null) {
            $recentActivity = AuditEntry::query()
                ->with('actor:id,name')
                ->where('company_id', $companyId)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'action', 'occurred_at', 'actor_user_id'])
                ->map(fn (AuditEntry $entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'occurred_at' => $entry->occurred_at?->toIso8601String(),
                    'actor_name' => $entry->actor?->name,
                ])
                ->values();
        }

        return response()->json([
            'data' => [
                'routines_pending_validation' => $pendingValidation,
                'routines_assigned' => $assigned,
                'routines_validated' => $validated,
                'invoices_draft' => $draftInvoices,
                'operations' => [
                    'routines_in_progress' => $inProgress,
                    'routines_submitted' => $submitted,
                    'routines_pending_billing' => $pendingBilling,
                    'routines_rejected' => $rejected,
                    'workflows_active' => $workflowsActive,
                    'status_breakdown' => $statusCounts,
                ],
                'catalog' => [
                    'assets' => Asset::query()->count(),
                    'sites' => Site::query()->count(),
                    'clients' => Client::query()->count(),
                    'suppliers' => Supplier::query()->count(),
                    'equipment_items' => CatalogItem::query()->count(),
                    'supply_items' => SupplyItem::query()->where('is_active', true)->count(),
                ],
                'design' => [
                    'forms' => FormDefinition::query()->count(),
                    'reports' => ReportTemplate::query()->count(),
                    'workflows' => WorkflowDefinition::query()->count(),
                    'routine_types' => RoutineType::query()->count(),
                ],
                'inventory' => [
                    'low_stock_count' => $lowStockCount,
                    'low_stock_items' => $lowStockItems,
                ],
                'focus_routines' => $focusRoutines,
                'recent_activity' => $recentActivity,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function routineCountsByStatus(): array
    {
        return Routine::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($count, $status) => [
                $status instanceof RoutineStatus ? $status->value : (string) $status => (int) $count,
            ])
            ->all();
    }

    private function focusRoutineOrderSql(): string
    {
        $pending = RoutineStatus::PendingValidation->value;
        $submitted = RoutineStatus::Submitted->value;
        $inProgress = RoutineStatus::InProgress->value;
        $assigned = RoutineStatus::Assigned->value;

        return "CASE status
            WHEN '{$pending}' THEN 0
            WHEN '{$submitted}' THEN 1
            WHEN '{$inProgress}' THEN 2
            WHEN '{$assigned}' THEN 3
            ELSE 9
        END";
    }
}
