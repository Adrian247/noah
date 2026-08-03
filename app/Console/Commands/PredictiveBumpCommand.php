<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Predictive\PredictiveAlgorithmVersionService;
use App\Support\DemoAccounts;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Incremento semver ofuscado de la UI (patch / major / minor explícito).
 */
class PredictiveBumpCommand extends Command
{
    protected $signature = 'phoenix:predictive:bump
                            {--patch : Incremento patch (x.y.z+1)}
                            {--major : Incremento major}
                            {--minor : Incremento minor (default)}
                            {--notes= : Notas opcionales}
                            {--user= : Email del actor (default root demo)}';

    protected $description = 'Crea una versión draft del algoritmo predictivo con bump explícito (uso ops/dev)';

    public function handle(PredictiveAlgorithmVersionService $service): int
    {
        $bump = 'minor';
        if ($this->option('patch')) {
            $bump = 'patch';
        } elseif ($this->option('major')) {
            $bump = 'major';
        }

        $email = (string) ($this->option('user') ?: DemoAccounts::ROOT_EMAIL);
        $actor = User::query()->where('email', $email)->first();
        if ($actor === null) {
            $this->error("Usuario no encontrado: {$email}");

            return self::FAILURE;
        }

        try {
            $version = $service->train($actor, [
                'bump' => $bump,
                'notes' => $this->option('notes') ?: "Bump {$bump} vía artisan",
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Draft creada: v%s (%s) id=%d',
            $version['semver'],
            $version['status'],
            $version['id'],
        ));

        return self::SUCCESS;
    }
}
