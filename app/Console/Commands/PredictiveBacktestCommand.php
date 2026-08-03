<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Predictive\PredictiveMaintenanceService;
use Illuminate\Console\Command;

class PredictiveBacktestCommand extends Command
{
    protected $signature = 'phoenix:predictive:backtest
                            {--company= : Id o nombre de la empresa}
                            {--horizon=14 : Ventana en días}
                            {--stride=7 : Días entre fechas de corte}';

    protected $description = 'Evalúa el motor predictivo contra el histórico ya cargado (prueba de regresión)';

    public function handle(PredictiveMaintenanceService $service): int
    {
        $company = ctype_digit(trim((string) $this->option('company')))
            ? Company::query()->find((int) $this->option('company'))
            : Company::query()
                ->when((string) $this->option('company') !== '', fn ($q) => $q->where('name', 'ilike', '%'.$this->option('company').'%'))
                ->orderBy('id')
                ->first();

        if ($company === null) {
            $this->error('No se encontró la empresa.');

            return self::FAILURE;
        }

        $report = $service->backtest(
            (int) $company->id,
            (int) $this->option('horizon'),
            (int) $this->option('stride'),
        );

        if (($report['rows'] ?? 0) === 0) {
            $this->error(implode(' ', $report['notes'] ?? ['Sin datos.']));

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s · ventana %d d · %s → %s',
            $company->name,
            $report['horizon_days'],
            $report['window']['from'],
            $report['window']['to'],
        ));
        $this->line(sprintf(
            'Observaciones: %d · fallas dentro de ventana: %d (tasa base %.1f %%) · ROC AUC: %s',
            $report['rows'],
            $report['positives'],
            $report['base_rate'] * 100,
            $report['roc_auc'] ?? 'n/d',
        ));

        if (($report['roc_auc_by_class'] ?? []) !== []) {
            $this->newLine();
            $this->table(
                ['Clase', 'Observaciones', 'Tasa base', 'ROC AUC dentro de la clase'],
                collect($report['roc_auc_by_class'])->map(fn (array $row, string $class) => [
                    $class,
                    $row['rows'],
                    sprintf('%.1f %%', $row['base_rate'] * 100),
                    $row['roc_auc'] ?? 'n/d',
                ])->values()->all(),
            );
        }

        $this->newLine();
        $this->table(
            ['Nivel de riesgo', 'Predicciones', 'Fallaron', 'Tasa observada'],
            collect($report['by_risk_level'])->map(fn (array $row, string $level) => [
                $level,
                $row['predicted'],
                $row['observed_failures'],
                sprintf('%.1f %%', $row['observed_rate'] * 100),
            ])->values()->all(),
        );

        $alert = $report['alert_metrics'];
        $this->line(sprintf(
            'Con alerta en p >= %.2f: precisión %s · recall %s · F1 %s (VP %d, FP %d, FN %d)',
            $alert['threshold'],
            $alert['precision'] ?? 'n/d',
            $alert['recall'] ?? 'n/d',
            $alert['f1'] ?? 'n/d',
            $alert['true_positive'],
            $alert['false_positive'],
            $alert['false_negative'],
        ));

        return self::SUCCESS;
    }
}
