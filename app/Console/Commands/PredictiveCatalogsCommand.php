<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Predictive\FailureModeCatalog;
use App\Support\Predictive\OemCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PredictiveCatalogsCommand extends Command
{
    protected $signature = 'phoenix:predictive:catalogs
                            {--company=* : Ids o nombres de empresa; vacío = todas}
                            {--only= : oem | failure-modes}';

    protected $description = 'Sincroniza OEM global, enlace a catálogo de equipos del tenant y modos de falla';

    public function handle(): int
    {
        $only = (string) $this->option('only');

        if ($only !== 'failure-modes') {
            $counts = OemCatalog::sync();
            $this->info(sprintf(
                'Catálogo OEM: %d modelos, %d planes, %d tareas.',
                $counts['models'],
                $counts['plans'],
                $counts['items'],
            ));
        }

        $companies = $this->resolveCompanies();
        if ($companies->isEmpty()) {
            $this->warn('No hay empresas para sincronizar.');

            return self::SUCCESS;
        }

        if ($only !== 'failure-modes') {
            foreach ($companies as $company) {
                $link = OemCatalog::linkCompanyCatalog((int) $company->id);
                $this->line(sprintf(
                    '  %s (#%d): OEM→catálogo %d enlazados, %d creados.',
                    $company->name,
                    $company->id,
                    $link['linked'],
                    $link['created'],
                ));
            }
        }

        if ($only === 'oem') {
            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $map = FailureModeCatalog::syncForCompany((int) $company->id);
            $this->line(sprintf('  %s (#%d): %d modos de falla.', $company->name, $company->id, count($map)));
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Company>
     */
    private function resolveCompanies(): Collection
    {
        $filters = array_filter((array) $this->option('company'));

        if ($filters === []) {
            return Company::query()->orderBy('id')->get();
        }

        return collect($filters)
            ->map(fn (string $filter) => ctype_digit($filter)
                ? Company::query()->find((int) $filter)
                : Company::query()->where('name', 'ilike', "%{$filter}%")->orderBy('id')->first())
            ->filter()
            ->values();
    }
}
