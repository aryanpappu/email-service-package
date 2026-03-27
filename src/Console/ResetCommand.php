<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;

class ResetCommand extends Command
{
    protected $signature   = 'smart-mailer:reset {provider : The provider key to reset} {--all : Reset all providers}';
    protected $description = 'Reset usage counters and cooling state for a provider';

    public function handle(ProviderPool $pool): int
    {
        if ($this->option('all')) {
            $statuses = $pool->getAllStatuses();
            foreach (array_keys($statuses) as $key) {
                $pool->resetProvider($key);
                $this->line("  Reset: <fg=green>{$key}</>");
            }
            $this->info('All providers reset successfully.');
            return self::SUCCESS;
        }

        $provider = $this->argument('provider');

        if (!$this->confirm("Reset all counters and cooling for provider [{$provider}]?")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $pool->resetProvider($provider);
        $this->info("Provider [{$provider}] has been reset.");

        return self::SUCCESS;
    }
}
