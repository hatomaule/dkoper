<?php

namespace App\Console\Commands;

use App\Services\FidesAccountDirectory;
use App\Services\FidesCooperativeProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegisterCurrentFidesCooperative extends Command
{
    protected $signature = 'fides:register-current {name} {slug}';

    protected $description = 'Register the current database as a FIDES cooperative and index its accounts';

    public function handle(FidesCooperativeProvisioner $provisioner, FidesAccountDirectory $directory): int
    {
        $cooperative = $provisioner->registerCurrent(
            (string) $this->argument('name'),
            (string) $this->argument('slug'),
        );

        $tenantManager = app(\App\Services\FidesTenantManager::class);
        $tenantManager->activate($cooperative);

        try {
            $count = $directory->synchronizeCooperative($cooperative);
        } finally {
            $tenantManager->restoreBaseConnection();
        }

        $this->info("Registered {$cooperative->name}; indexed {$count} accounts.");

        return self::SUCCESS;
    }
}
