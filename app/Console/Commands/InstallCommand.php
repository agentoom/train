<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Dataset\DatasetSettingsImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('agentoom-train:install')]
#[Description('Install Agentoom Train and create the default superadmin user')]
class InstallCommand extends Command
{
    public function handle(): int
    {
        $email = 'superadmin@agentoom.com';

        if (User::where('email', $email)->exists()) {
            $this->warn('Default superadmin user already exists. Skipping creation.');

            return self::SUCCESS;
        }

        $user = User::create([
            'name' => 'superadmin',
            'email' => $email,
            'password' => Hash::make('changeme'),
        ]);

        $this->info('Agentoom Train installed successfully.');
        $this->info('Default superadmin user created:');
        $this->line('  Email:    superadmin@agentoom.com');
        $this->line('  Password: changeme');

        $this->importDefaultDatasets($user);

        return self::SUCCESS;
    }

    private function importDefaultDatasets(User $user): void
    {
        $entries = config('agentoom-train-data', []);

        if (empty($entries)) {
            return;
        }

        // The config file stores the JSON export as the first (and only) array value.
        $json = reset($entries);

        if (! is_string($json)) {
            return;
        }

        try {
            $importService = app(DatasetSettingsImportService::class);
            $datasets = $importService->parse($json);
            $created = $importService->import($datasets, $user);

            $count = count($created);
            $this->info("Imported {$count} default dataset(s) from config.");
        } catch (\InvalidArgumentException $e) {
            $this->warn('Could not import default datasets: '.$e->getMessage());
        }
    }
}
