<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallFidesPlatform extends Command
{
    protected $signature = 'fides:install';

    protected $description = 'Create or update FIDES platform catalog tables';

    public function handle(): int
    {
        $exitCode = $this->call('migrate', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error('FIDES platform migration failed.');

            return self::FAILURE;
        }

        $this->info('FIDES platform tables are ready.');

        return self::SUCCESS;
    }
}
