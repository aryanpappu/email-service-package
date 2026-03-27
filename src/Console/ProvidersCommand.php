<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\ProviderFactory;

class ProvidersCommand extends Command
{
    protected $signature   = 'smart-mailer:providers';
    protected $description = 'List all configured email providers and their settings';

    public function handle(ProviderFactory $factory): int
    {
        $config    = config('smart-mailer');
        $providers = $config['providers'] ?? [];

        if (empty($providers)) {
            $this->warn('No providers configured. Publish and edit config/smart-mailer.php.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('  <fg=cyan>Configured Providers</>');

        $rows = [];
        foreach ($providers as $key => $cfg) {
            $rows[] = [
                $key,
                $cfg['driver'] ?? '—',
                ($cfg['enabled'] ?? true) ? '<fg=green>Yes</>' : '<fg=red>No</>',
                $cfg['priority'] ?? '—',
                $cfg['daily_limit'] ?? '∞',
                $cfg['hourly_limit'] ?? '∞',
                $cfg['from_email'] ?? '(from message)',
            ];
        }

        $this->table(
            ['Key', 'Driver', 'Enabled', 'Priority', 'Daily Limit', 'Hourly Limit', 'From Email'],
            $rows,
        );

        $this->info('');
        $this->info('  <fg=cyan>Domain Routing</>');

        $domainRows = [];
        foreach ($config['domains'] ?? [] as $domain => $domainCfg) {
            $domainRows[] = [
                $domain,
                $domainCfg['strategy'] ?? $config['defaults']['strategy'] ?? 'priority',
                implode(', ', $domainCfg['providers'] ?? []),
                $domainCfg['from_email'] ?? '—',
            ];
        }

        $this->table(['Domain', 'Strategy', 'Providers', 'From Email'], $domainRows);

        $this->info('');
        $this->info('  <fg=cyan>Registered Drivers</>: ' . implode(', ', $factory->getRegisteredDrivers()));
        $this->info('');

        return self::SUCCESS;
    }
}
