<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\NoahDemoSeeder;
use Illuminate\Console\Command;

class EnsureDemoDataCommand extends Command
{
    protected $signature = 'noah:ensure-demo
                            {--reset-credentials : Restablece contraseñas de cuentas demo (noah.demo_password)}';

    protected $description = 'Seed demo data when missing; optional reset of demo login passwords';

    /** @var list<array{email: string, name: string}> */
    private const DEMO_ACCOUNTS = [
        ['email' => 'admin@noah.local', 'name' => 'Administrador Noah'],
        ['email' => 'tecnico@noah.local', 'name' => 'Técnico Demo'],
        ['email' => 'supervisor@noah.local', 'name' => 'Supervisor Demo'],
        ['email' => 'facturacion@noah.local', 'name' => 'Facturación Demo'],
    ];

    /**
     * @return list<string>
     */
    public static function demoAccountEmails(): array
    {
        return array_column(self::DEMO_ACCOUNTS, 'email');
    }

    public function handle(): int
    {
        if ($this->demoEnvironmentIncomplete()) {
            $this->call('db:seed', ['--class' => NoahDemoSeeder::class, '--force' => true]);
            $this->info('Demo data seeded (entorno incompleto o vacío).');
        }

        if ($this->option('reset-credentials')) {
            $this->resetDemoCredentials();
            $this->call('noah:bootstrap-permissions');
            $this->info('Demo credentials reset (password: '.config('noah.demo_password').').');

            return self::SUCCESS;
        }

        if (! User::query()->where('email', 'admin@noah.local')->exists()) {
            $this->info('Demo admin missing after seed attempt; check NoahDemoSeeder.');
        } else {
            $this->info('Demo admin present. Use --reset-credentials or noah:refresh-demo if login fails.');
        }

        $this->call('noah:bootstrap-permissions');

        return self::SUCCESS;
    }

    private function demoEnvironmentIncomplete(): bool
    {
        if (! User::query()->where('email', 'admin@noah.local')->exists()) {
            return true;
        }

        return ! Company::query()->where('name', 'Demo Industrial')->exists();
    }

    private function resetDemoCredentials(): void
    {
        foreach (self::DEMO_ACCOUNTS as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => config('noah.demo_password')],
            );
        }
    }
}
