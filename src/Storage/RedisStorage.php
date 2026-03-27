<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Storage;

use Carbon\Carbon;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use TechSolutionStuff\SmartMailer\Contracts\UsageStorage;

/**
 * Redis storage using atomic INCR/EXPIRE commands.
 * Most reliable for high-concurrency queue environments.
 */
class RedisStorage implements UsageStorage
{
    public function __construct(private readonly RedisConnection $redis) {}

    public function getSentToday(string $providerKey): int
    {
        return (int) $this->redis->get($this->dailyKey($providerKey));
    }

    public function getSentThisHour(string $providerKey): int
    {
        return (int) $this->redis->get($this->hourlyKey($providerKey));
    }

    public function getSentTotal(string $providerKey): int
    {
        return (int) $this->redis->get("smart_mailer:total:{$providerKey}");
    }

    public function incrementSent(string $providerKey): void
    {
        // INCR is atomic in Redis — safe for concurrent workers
        $dailyKey = $this->dailyKey($providerKey);
        $count = $this->redis->incr($dailyKey);
        if ($count === 1) {
            // Set TTL on first write only
            $this->redis->expireat($dailyKey, Carbon::tomorrow()->timestamp);
        }

        $hourlyKey = $this->hourlyKey($providerKey);
        $hourCount = $this->redis->incr($hourlyKey);
        if ($hourCount === 1) {
            $nextHour = Carbon::now()->addHour()->startOfHour()->timestamp;
            $this->redis->expireat($hourlyKey, $nextHour);
        }

        $this->redis->incr("smart_mailer:total:{$providerKey}");
        $this->redis->set("smart_mailer:last_used:{$providerKey}", Carbon::now()->timestamp);
    }

    public function getConsecutiveFailures(string $providerKey): int
    {
        return (int) $this->redis->get("smart_mailer:failures:{$providerKey}");
    }

    public function incrementFailures(string $providerKey): void
    {
        $this->redis->incr("smart_mailer:failures:{$providerKey}");
    }

    public function resetFailures(string $providerKey): void
    {
        $this->redis->del("smart_mailer:failures:{$providerKey}");
    }

    public function getCoolingUntil(string $providerKey): ?int
    {
        $value = $this->redis->get("smart_mailer:cooling:{$providerKey}");
        return $value !== null ? (int) $value : null;
    }

    public function setCooling(string $providerKey, int $minutes): void
    {
        $until = Carbon::now()->addMinutes($minutes)->timestamp;
        $this->redis->setex("smart_mailer:cooling:{$providerKey}", $minutes * 2 * 60, $until);
    }

    public function clearCooling(string $providerKey): void
    {
        $this->redis->del("smart_mailer:cooling:{$providerKey}");
    }

    public function getLastUsedAt(string $providerKey): ?int
    {
        $value = $this->redis->get("smart_mailer:last_used:{$providerKey}");
        return $value !== null ? (int) $value : null;
    }

    public function resetDaily(string $providerKey): void
    {
        $this->redis->del($this->dailyKey($providerKey));
    }

    public function resetHourly(string $providerKey): void
    {
        $this->redis->del($this->hourlyKey($providerKey));
    }

    public function getAllStats(string $providerKey): array
    {
        return [
            'sent_today'           => $this->getSentToday($providerKey),
            'sent_this_hour'       => $this->getSentThisHour($providerKey),
            'sent_total'           => $this->getSentTotal($providerKey),
            'consecutive_failures' => $this->getConsecutiveFailures($providerKey),
            'cooling_until'        => $this->getCoolingUntil($providerKey),
            'last_used_at'         => $this->getLastUsedAt($providerKey),
        ];
    }

    private function dailyKey(string $providerKey): string
    {
        return 'smart_mailer:daily:' . $providerKey . ':' . Carbon::today()->format('Y-m-d');
    }

    private function hourlyKey(string $providerKey): string
    {
        return 'smart_mailer:hourly:' . $providerKey . ':' . Carbon::now()->format('Y-m-d-H');
    }
}
