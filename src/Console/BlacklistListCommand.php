<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\SpamGuard;

class BlacklistListCommand extends Command
{
    protected $signature   = 'smart-mailer:blacklist:list';
    protected $description = 'List all blacklisted emails and domains';

    public function handle(SpamGuard $guard): int
    {
        $entries = $guard->getBlacklist();

        if (empty($entries)) {
            $this->info('Blacklist is empty.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $target => $meta) {
            $rows[] = [
                $target,
                $meta['permanent'] ? '<fg=red>Permanent</>' : '<fg=yellow>Temporary</>',
                $meta['added_at'] ?? '—',
                $meta['expires_at'] ?? '—',
            ];
        }

        $this->table(['Target', 'Type', 'Added At', 'Expires At'], $rows);
        $this->info(count($entries) . ' entries in blacklist.');

        return self::SUCCESS;
    }
}
