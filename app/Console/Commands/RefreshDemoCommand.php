<?php

namespace App\Console\Commands;

use Database\Seeders\PhoenixDemoSeeder;
use App\Support\DemoAccounts;
use Illuminate\Console\Command;

/**
 * Ritual estándar tras cambios en seeders, permisos o datos demo.
 * Ver docs/DEMO_ENV.md
 */
class RefreshDemoCommand extends Command
{
    protected $signature = 'phoenix:refresh-demo
                            {--skip-migrate : No ejecutar migrate}';

    protected $description = 'Reaplica PhoenixDemoSeeder, permisos y restablece credenciales demo';

    public function handle(): int
    {
        if (! $this->option('skip-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('phoenix:bootstrap-permissions');
        $this->call('db:seed', ['--class' => PhoenixDemoSeeder::class, '--force' => true]);
        $this->call('phoenix:ensure-demo', ['--reset-credentials' => true]);

        $this->newLine();
        $this->info('Demo listo. Cuenta por defecto: '.DemoAccounts::DEFAULT_LOGIN_EMAIL.' / '.config('phoenix.demo_password'));
        $this->info('Root plataforma: '.DemoAccounts::ROOT_EMAIL.' / '.config('phoenix.demo_root_password'));
        $this->line('Otras cuentas (misma contraseña tenant):');
        foreach (EnsureDemoDataCommand::demoAccountEmails() as $email) {
            if ($email === DemoAccounts::DEFAULT_LOGIN_EMAIL) {
                continue;
            }
            $this->line('  · '.$email);
        }

        return self::SUCCESS;
    }
}
