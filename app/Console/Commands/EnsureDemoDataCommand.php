<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\NoahDemoSeeder;
use Illuminate\Console\Command;

class EnsureDemoDataCommand extends Command
{
    protected $signature = 'noah:ensure-demo';

    protected $description = 'Seed demo users and data when the database has no users';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('Users already present; skipping demo seed.');
            $this->call('noah:bootstrap-permissions');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => NoahDemoSeeder::class, '--force' => true]);
        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }
}
