<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Predictive\LogbookIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

class PredictiveIngestCommand extends Command
{
    protected $signature = 'phoenix:predictive:ingest
                            {file : Ruta del JSON generado por ml/phoenix-predict/scripts/extract_logbooks.py}
                            {--company= : Id o nombre de la empresa destino}
                            {--backfill-hour-meter : Deriva la serie de horómetro a mediciones}';

    protected $description = 'Ingesta una bitácora de mantenimiento normalizada al modelo predictivo';

    public function handle(LogbookIngestService $ingest): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $company = $this->resolveCompany();
        if ($company === null) {
            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $dataset */
            $dataset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('JSON inválido: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line("Empresa: {$company->name} (#{$company->id})");
        $this->line('Perfil: '.(data_get($dataset, 'meta.profile') ?? 'desconocido'));
        $this->line('Origen: '.(data_get($dataset, 'meta.source_file') ?? basename($path)));

        try {
            $counts = DB::transaction(fn () => $ingest->ingest((int) $company->id, $dataset));
        } catch (Throwable $e) {
            $this->error('Falló la ingesta: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('backfill-hour-meter')) {
            $counts['hour_meter_measurements'] = $ingest->backfillHourMeterMeasurements((int) $company->id);
        }

        $this->newLine();
        $this->table(
            ['Entidad', 'Registros'],
            collect($counts)->map(fn (int $total, string $key) => [$key, $total])->values()->all(),
        );

        return self::SUCCESS;
    }

    private function resolveCompany(): ?Company
    {
        $option = trim((string) $this->option('company'));

        if ($option === '') {
            $company = Company::query()->orderBy('id')->first();
            if ($company === null) {
                $this->error('No hay empresas registradas. Usa --company o siembra la demo.');
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
