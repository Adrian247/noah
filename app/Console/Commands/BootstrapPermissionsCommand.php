<?php

namespace App\Console\Commands;

use App\Services\Identity\CompanyAuthorizationService;
use Illuminate\Console\Command;

class BootstrapPermissionsCommand extends Command
{
    protected $signature = 'phoenix:bootstrap-permissions';

    protected $description = 'Sync permission catalog, company roles, and membership role assignments (Spatie)';

    public function handle(CompanyAuthorizationService $authorization): int
    {
        $authorization->bootstrapAllCompanies();
        $this->info('Permissions and roles synchronized.');

        return self::SUCCESS;
    }
}
