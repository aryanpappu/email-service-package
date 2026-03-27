<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Storage;

use Carbon\Carbon;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use TechSolutionStuff\SmartMailer\Contracts\UsageStorage;

class CacheStorage implements UsageStorage
{
    private const LOCK_TIMEOUT = 5;

    public function __construct(private readonly Cache $cache) {}

    public function getSentToday(string $providerKey): int
    {
        return (int) $this->cache->get($this->dailyKey($providerKey), 0);
    }

    public function getSentThisHour(string $providerKey): int
    {
        return (int) $this->cache->get($this->hourlyKey($providerKey), 0);
    }

    public function getSentTotal(string $providerKey): int
    {
        return (int) $this->cache->get("smart_mailer:total:{$providerKey}", 0);
    }

    public function incrementSent(string $providerKey): void
    {
        $lock = $this->cache->lock("smart_mailer:lock:{$providerKey}", self::LOCK_TIMEOUT);

        try {
            // HIGH FIX: Catch LockTimeoutException to prevent crashing on contention
            $lock->block(self::LOCK_TIMEOUT, function () use ($providerKey): void {
                // Daily counter — TTL until end of current day
                $dailyKey = $this->dailyKey($providerKey);
                $dailyTtl = Carbon::now()->secondsUntilEndOfDay() + 1;
                $daily    = (int) $this->cache->get($dailyKey, 0);
                $this->cache->put($dailyKey, $daily + 1, $dailyTtl);

                // Hourly counter — TTL until end of current hour
                $hourlyKey = $this->hourlyKey($providerKey);
                $hourlyTtl = (60 - Carbon::now()->minute) * 60 - Carbon::now()->second + 1;
                $hourly    = (int) $this->cache->get($hourlyKey, 0);
                $this->cache->put($hourlyKey, $hourly + 1, max(1, $hourlyTtl));

                // Total — no expiry
                $this->cache->increment("smart_mailer:total:{$providerKey}");

                // Last used
                $this->cache->forever("smart_mailer:last_used:{$providerKey}", Carbon::now()->timestamp);
            });
        } catch (LockTimeoutException) {
            // Could not acquire lock within timeout — still attempt a best-effort increment
            // This is safe: slightly over-counting is better than crashing a send
            $dailyKey = $this->dailyKey($providerKey);
            $dailyTtl = Carbon::now()->secondsUntilEndOfDay() + 1;
            $this->cache->put($dailyKey, (int) $this->cache->get($dailyKey, 0) + 1, $dailyTtl);
        }
    }

    public function getConsecutiveFailures(string $providerKey): int
    {
        return (int) $this->cache->get("smart_mailer:failures:{$providerKey}", 0);
    }

    public function incrementFailures(string $providerKey): void
    {
        $this->cache->increment("smart_mailer:failures:{$providerKey}");
    }

    public function resetFailures(string $providerKey): void
    {
        $this->cache->forget("smart_mailer:failures:{$providerKey}");
    }

    public function getCoolingUntil(string $providerKey): ?int
    {
        $value = $this->cache->get("smart_mailer:cooling:{$providerKey}");
        return $value !== null ? (int) $value : null;
    }

    public function setCooling(string $providerKey, int $minutes): void
    {
        $until = Carbon::now()->addMinutes($minutes)->timestamp;
        $this->cache->put("smart_mailer:cooling:{$providerKey}", $until, $minutes * 2 * 60);
    }

    public function clearCooling(string $providerKey): void
    {
        $this->cache->forget("smart_mailer:cooling:{$providerKey}");
    }

    public function getLastUsedAt(string $providerKey): ?int
    {
        $value = $this->cache->get("smart_mailer:last_used:{$providerKey}");
        return $value !== null ? (int) $value : null;
    }

    public function resetDaily(string $providerKey): void
    {
        $this->cache->forget($this->dailyKey($providerKey));
    }

    public function resetHourly(string $providerKey): void
    {
        $this->cache->forget($this->hourlyKey($providerKey));
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
