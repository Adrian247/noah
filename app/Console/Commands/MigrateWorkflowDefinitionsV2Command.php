<?php

namespace App\Console\Commands;

use App\Models\WorkflowDefinition;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Console\Command;

class MigrateWorkflowDefinitionsV2Command extends Command
{
    protected $signature = 'noah:migrate-workflow-definitions-v2 {--company= : Company ID}';

    protected $description = 'Actualiza definiciones de workflow al grafo v2 (facturación antes del cierre).';

    public function handle(): int
    {
        $query = WorkflowDefinition::query()->withoutGlobalScopes();
        if ($this->option('company')) {
            $query->where('company_id', (int) $this->option('company'));
        }

        $definition = WorkflowRuntime::defaultDefinition();
        $count = 0;

        $query->each(function (WorkflowDefinition $row) use ($definition, &$count): void {
            $row->update(['definition' => $definition]);
            $count++;
        });

        $this->info("Actualizadas {$count} definiciones.");

        return self::SUCCESS;
    }
}
