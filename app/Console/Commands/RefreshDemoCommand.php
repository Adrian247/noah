<?php

namespace App\Console\Commands;

use Database\Seeders\NoahDemoSeeder;
use Illuminate\Console\Command;

/**
 * Ritual estándar tras cambios en seeders, permisos o datos demo.
 * Ver docs/DEMO_ENV.md
 */
class RefreshDemoCommand extends Command
{
    protected $signature = 'noah:refresh-demo
                            {--skip-migrate : No ejecutar migrate}';

    protected $description = 'Reaplica NoahDemoSeeder, permisos y restablece credenciales demo';

    public function handle(): int
    {
        if (! $this->option('skip-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('noah:bootstrap-permissions');
        $this->call('db:seed', ['--class' => NoahDemoSeeder::class, '--force' => true]);
        $this->call('noah:ensure-demo', ['--reset-credentials' => true]);

        $password = config('noah.demo_password');
        $this->newLine();
        $this->info('Demo listo. Credenciales (@noah.local / '.$password.'):');
        foreach (EnsureDemoDataCommand::demoAccountEmails() as $email) {
            $this->line('  · '.$email);
        }

        return self::SUCCESS;
    }
}
