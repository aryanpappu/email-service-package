<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\SpamGuard;

class BlacklistAddCommand extends Command
{
    protected $signature = 'smart-mailer:blacklist:add
        {target : Email address or domain to blacklist}
        {--duration= : Duration in minutes (omit for permanent)}';

    protected $description = 'Add an email or domain to the SmartMailer blacklist';

    public function handle(SpamGuard $guard): int
    {
        $target   = $this->argument('target');
        $duration = $this->option('duration') ? (int) $this->option('duration') : null;

        $guard->addToBlacklist($target, $duration);

        $msg = $duration
            ? "Added [{$target}] to blacklist for {$duration} minutes."
            : "Added [{$target}] to blacklist permanently.";

        $this->info($msg);

        return self::SUCCESS;
    }
}
