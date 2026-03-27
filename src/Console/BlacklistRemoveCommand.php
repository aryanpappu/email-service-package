<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\SpamGuard;

class BlacklistRemoveCommand extends Command
{
    protected $signature   = 'smart-mailer:blacklist:remove {target : Email address or domain to remove}';
    protected $description = 'Remove an email or domain from the SmartMailer blacklist';

    public function handle(SpamGuard $guard): int
    {
        $target = $this->argument('target');
        $guard->removeFromBlacklist($target);
        $this->info("Removed [{$target}] from blacklist.");

        return self::SUCCESS;
    }
}
