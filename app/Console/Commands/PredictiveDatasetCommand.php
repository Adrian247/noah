<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Predictive\PredictiveMaintenanceService;
use Illuminate\Console\Command;

class PredictiveDatasetCommand extends Command
{
    protected $signature = 'phoenix:predictive:dataset
                            {--company= : Id o nombre de la empresa}
                            {--horizon=14 : Ventana de la etiqueta, en días}
                            {--stride=7 : Días entre fechas de corte}
                            {--out=ml/phoenix-predict/data/training.json : Archivo de salida}';

    protected $description = 'Exporta el dataset supervisado (features + etiqueta) para entrenar el modelo del subproyecto ML';

    public function handle(PredictiveMaintenanceService $service): int
    {
        $company = $this->resolveCompany();
        if ($company === null) {
            return self::FAILURE;
        }

        $dataset = $service->trainingDataset(
            (int) $company->id,
            (int) $this->option('horizon'),
            (int) $this->option('stride'),
        );

        if ($dataset['rows'] === []) {
            $this->error('No hay bitácoras suficientes para armar el dataset.');

            return self::FAILURE;
        }

        $path = (string) $this->option('out');
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        file_put_contents($path, json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $positiveRate = $dataset['total'] > 0 ? $dataset['positives'] / $dataset['total'] : 0;
        $this->info(sprintf(
            '%s · %d filas, %d positivas (%.1f %%), ventana %s → %s',
            $path,
            $dataset['total'],
            $dataset['positives'],
            $positiveRate * 100,
            $dataset['window']['from'],
            $dataset['window']['to'],
        ));

        if ($positiveRate < 0.05 || $positiveRate > 0.95) {
            $this->warn('La etiqueta está muy desbalanceada; entrena con class_weight y revisa la ventana.');
        }

        return self::SUCCESS;
    }

    private function resolveCompany(): ?Company
    {
        $option = trim((string) $this->option('company'));

        if ($option === '') {
            $company = Company::query()->orderBy('id')->first();
            if ($company === null) {
                $this->error('No hay empresas registradas.');
            }

            return $company;
        }

        $company = ctype_digit($option)
            ? Company::query()->find((int) $option)
            : Company::query()->where('name', 'ilike', "%{$option}%")->orderBy('id')->first();

        if ($company === null) {
            $this->error("No se encontró la empresa '{$option}'.");
        }

        return $company;
    }
}
