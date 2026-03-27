<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;

class StatusCommand extends Command
{
    protected $signature   = 'smart-mailer:status {--provider= : Show status for a specific provider}';
    protected $description = 'Show status, usage counters, and limits for all email providers';

    public function handle(ProviderPool $pool): int
    {
        $statuses = $pool->getAllStatuses();

        if ($provider = $this->option('provider')) {
            $statuses = array_filter($statuses, fn ($s) => $s->key === $provider);
        }

        if (empty($statuses)) {
            $this->warn('No providers found.');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('  <fg=cyan>SmartMailer Provider Status</>');
        $this->info('  ' . str_repeat('─', 90));

        $rows = [];
        foreach ($statuses as $status) {
            $unlimited = $status->dailyLimit === 0;

            // MEDIUM FIX: Don't show EXHAUSTED/LOW for providers with no daily limit set
            $statusLabel = match (true) {
                !$status->enabled                                   => '<fg=gray>DISABLED</>',
                $status->isCooling                                  => '<fg=yellow>COOLING</>',
                !$unlimited && $status->remainingToday === 0        => '<fg=red>EXHAUSTED</>',
                !$unlimited && $status->remainingToday < 10         => '<fg=yellow>LOW</>',
                default                                             => '<fg=green>OK</>',
            };

            $todayDisplay     = $unlimited
                ? "{$status->sentToday}/∞"
                : "{$status->sentToday}/{$status->dailyLimit}";

            $hourlyDisplay    = $status->hourlyLimit === 0
                ? "{$status->sentThisHour}/∞"
                : "{$status->sentThisHour}/{$status->hourlyLimit}";

            $remainingDisplay = $unlimited ? '∞' : (string) $status->remainingToday;

            $rows[] = [
                $status->key,
                $status->driver,
                $statusLabel,
                $todayDisplay,
                $hourlyDisplay,
                $remainingDisplay,
                $status->consecutiveFailures,
                $status->isCooling ? ($status->coolingUntil ?? '-') : '-',
                $status->lastUsedAt ?? 'Never',
            ];
        }

        $this->table(
            ['Key', 'Driver', 'Status', 'Today', 'This Hour', 'Remaining', 'Failures', 'Cooling Until', 'Last Used'],
            $rows,
        );

        $totalSentToday = array_sum(array_map(fn ($s) => $s->sentToday, $statuses));
        $limitedStatuses = array_filter($statuses, fn ($s) => $s->dailyLimit > 0);
        $totalRemaining  = array_sum(array_map(fn ($s) => $s->remainingToday, $limitedStatuses));

        $this->info('');
        $this->line("  Total sent today: <fg=cyan>{$totalSentToday}</> | Remaining (limited providers): <fg=green>{$totalRemaining}</>");
        $this->info('');

        return self::SUCCESS;
    }
}
