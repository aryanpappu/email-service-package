<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;

class UsageCommand extends Command
{
    protected $signature = 'smart-mailer:usage
        {--provider= : Filter by provider key}
        {--json : Output as JSON}';

    protected $description = 'Show email usage report across all providers';

    public function handle(ProviderPool $pool): int
    {
        $statuses = $pool->getAllStatuses();

        if ($provider = $this->option('provider')) {
            $statuses = array_filter($statuses, fn ($s) => $s->key === $provider);
        }

        if ($this->option('json')) {
            $data = array_map(fn ($s) => [
                'key'                 => $s->key,
                'driver'              => $s->driver,
                'sent_today'          => $s->sentToday,
                'sent_this_hour'      => $s->sentThisHour,
                'sent_total'          => $s->sentTotal,
                'daily_limit'         => $s->dailyLimit,
                'hourly_limit'        => $s->hourlyLimit,
                'remaining_today'     => $s->remainingToday,
                'remaining_this_hour' => $s->remainingThisHour,
                'is_cooling'          => $s->isCooling,
            ], array_values($statuses));

            $this->line(json_encode($data, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $totalToday = 0;
        $totalLimit = 0;

        $rows = [];
        foreach ($statuses as $s) {
            $pct         = $s->dailyLimit > 0 ? round(($s->sentToday / $s->dailyLimit) * 100, 1) : 0;
            $bar         = $this->progressBar($pct);
            $totalToday += $s->sentToday;
            $totalLimit += $s->dailyLimit;

            $rows[] = [
                $s->key,
                $s->sentToday,
                $s->dailyLimit,
                $s->remainingToday,
                "{$pct}% {$bar}",
                $s->sentTotal,
            ];
        }

        $this->info('');
        $this->info('  <fg=cyan>Daily Usage Report</>');
        $this->table(['Provider', 'Sent Today', 'Daily Limit', 'Remaining', 'Usage', 'Total Ever'], $rows);

        $totalPct = $totalLimit > 0 ? round(($totalToday / $totalLimit) * 100, 1) : 0;
        $this->line("  Combined: {$totalToday}/{$totalLimit} ({$totalPct}% used)");
        $this->info('');

        return self::SUCCESS;
    }

    private function progressBar(float $pct): string
    {
        $filled = (int) round($pct / 10);
        $empty  = 10 - $filled;
        $color  = $pct >= 90 ? 'red' : ($pct >= 70 ? 'yellow' : 'green');
        return '<fg=' . $color . '>' . str_repeat('█', $filled) . '</>' . str_repeat('░', $empty);
    }
}
