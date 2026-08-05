<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\Identity\ClientPortalAccountService;
use App\Support\DemoAccounts;
use App\Support\PlatformAdmin;
use Database\Seeders\PhoenixDemoSeeder;
use App\Services\Workflow\WorkflowRuntime;
use Illuminate\Console\Command;

class EnsureDemoDataCommand extends Command
{
    protected $signature = 'phoenix:ensure-demo
                            {--reset-credentials : Restablece contraseñas demo (root y tenants)}';

    protected $description = 'Seed demo data when missing; optional reset of demo login passwords';

    private const DEMO_COMPANY_NAMES = [
        'Mein Company',
        'Dom-G',
        'Sandbox',
    ];

    /**
     * @return list<string>
     */
    public static function demoAccountEmails(): array
    {
        return DemoAccounts::allEmails();
    }

    public function handle(): int
    {
        if ($this->demoEnvironmentIncomplete()) {
            $this->call('db:seed', ['--class' => PhoenixDemoSeeder::class, '--force' => true]);
            $this->info('Demo data seeded (entorno incompleto o vacío).');
        }

        if ($this->option('reset-credentials')) {
            if ($this->demoEnvironmentIncomplete()) {
                $this->call('db:seed', ['--class' => PhoenixDemoSeeder::class, '--force' => true]);
            }
            $this->resetDemoCredentials();
            $this->call('phoenix:bootstrap-permissions');
            $this->info('Demo credentials reset (root: '.config('phoenix.demo_root_password').', tenants: '.config('phoenix.demo_password').').');
            $this->syncWorkflowsAndPortalAccounts();

            return self::SUCCESS;
        }

        if (! User::query()->where('email', DemoAccounts::ROOT_EMAIL)->exists()) {
            $this->info('Demo admin missing after seed attempt; check PhoenixDemoSeeder.');
        } else {
            $this->info('Demo admin present. Use --reset-credentials or phoenix:refresh-demo if login fails.');
        }

        $this->call('phoenix:bootstrap-permissions');
        $this->syncWorkflowsAndPortalAccounts();

        return self::SUCCESS;
    }

    private function syncWorkflowsAndPortalAccounts(): void
    {
        $workflowRuntime = app(WorkflowRuntime::class);
        $synced = 0;
        foreach (self::DEMO_COMPANY_NAMES as $companyName) {
            $company = Company::query()->where('name', $companyName)->first();
            if ($company === null) {
                continue;
            }
            $workflowRuntime->syncStandardWorkflowForCompany($company->id);
            $synced++;
        }
        if ($synced > 0) {
            $this->info("Workflow estándar «routine-validation-v1» sincronizado en {$synced} empresa(s) demo.");
        }

        $portalSynced = app(ClientPortalAccountService::class)->syncAllCompanies();
        if ($portalSynced > 0) {
            $this->info("Cuentas de portal de cliente sincronizadas: {$portalSynced} (password: ".ClientPortalAccountService::portalPassword().').');
        }
    }

    private function demoEnvironmentIncomplete(): bool
    {
        if (! User::query()->where('email', DemoAccounts::ROOT_EMAIL)->exists()) {
            return true;
        }

        return ! Company::query()->where('name', self::DEMO_COMPANY_NAMES[0])->exists();
    }

    private function resetDemoCredentials(): void
    {
        foreach (DemoAccounts::allEmails() as $email) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                continue;
            }

            $user->forceFill([
                'password' => DemoAccounts::passwordForEmail($email),
                'is_platform_admin' => $email === DemoAccounts::ROOT_EMAIL,
            ])->save();

            PlatformAdmin::syncFlagFromConfig($user);
        }
    }
}
