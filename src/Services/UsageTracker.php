<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Services;

use Carbon\Carbon;
use TechSolutionStuff\SmartMailer\Contracts\UsageStorage;
use TechSolutionStuff\SmartMailer\DTOs\ProviderStatus;

class UsageTracker
{
    public function __construct(
        private readonly UsageStorage $storage,
        private readonly array $config,
    ) {}

    public function canSend(string $providerKey): bool
    {
        if ($this->isCooling($providerKey)) {
            return false;
        }

        $providerConfig = $this->config['providers'][$providerKey] ?? [];

        if (empty($providerConfig) || !($providerConfig['enabled'] ?? true)) {
            return false;
        }

        $dailyLimit  = (int) ($providerConfig['daily_limit'] ?? 0);
        $hourlyLimit = (int) ($providerConfig['hourly_limit'] ?? 0);

        // 0 means unlimited — skip limit check
        if ($dailyLimit > 0 && $this->storage->getSentToday($providerKey) >= $dailyLimit) {
            return false;
        }

        if ($hourlyLimit > 0 && $this->storage->getSentThisHour($providerKey) >= $hourlyLimit) {
            return false;
        }

        return true;
    }

    /**
     * Pure read — does NOT clear cooling as a side-effect.
     * HIGH FIX: Cooling expiry clear is separated from the read check to avoid
     * hidden side-effects when called inside array_filter() in ProviderPool.
     */
    public function isCooling(string $providerKey): bool
    {
        $coolingUntil = $this->storage->getCoolingUntil($providerKey);

        if ($coolingUntil === null) {
            return false;
        }

        return Carbon::now()->timestamp < $coolingUntil;
    }

    /**
     * Clear expired cooling state. Call this when actually selecting a provider,
     * not inside filter predicates.
     */
    public function clearExpiredCooling(string $providerKey): void
    {
        $coolingUntil = $this->storage->getCoolingUntil($providerKey);
        if ($coolingUntil !== null && Carbon::now()->timestamp >= $coolingUntil) {
            $this->storage->clearCooling($providerKey);
        }
    }

    public function recordSuccess(string $providerKey): void
    {
        $this->storage->incrementSent($providerKey);
        $this->storage->resetFailures($providerKey);
        $this->storage->clearCooling($providerKey);
    }

    public function recordFailure(string $providerKey): void
    {
        $this->storage->incrementFailures($providerKey);

        $failures = $this->storage->getConsecutiveFailures($providerKey);

        // HIGH FIX: Use dedicated config key for provider cooling — not spam blacklist threshold
        $maxFailures = (int) ($this->config['defaults']['max_failures_before_cooling'] ?? 5);
        $coolingMins = (int) ($this->config['defaults']['cooling_minutes'] ?? 60);

        if ($failures >= $maxFailures) {
            $this->storage->setCooling($providerKey, $coolingMins);
        }
    }

    public function setCooling(string $providerKey, ?int $minutes = null): void
    {
        $minutes = $minutes ?? (int) ($this->config['defaults']['cooling_minutes'] ?? 60);
        $this->storage->setCooling($providerKey, $minutes);
    }

    public function getStatus(string $providerKey): ProviderStatus
    {
        $providerConfig = $this->config['providers'][$providerKey] ?? [];
        $stats          = $this->storage->getAllStats($providerKey);
        $dailyLimit     = (int) ($providerConfig['daily_limit'] ?? 0);
        $hourlyLimit    = (int) ($providerConfig['hourly_limit'] ?? 0);
        $coolingUntil   = $stats['cooling_until'];
        $isCooling      = $coolingUntil !== null && Carbon::now()->timestamp < $coolingUntil;

        // MEDIUM FIX: Use PHP_INT_MAX for unlimited so remaining calculates correctly
        $effectiveDailyLimit  = $dailyLimit > 0 ? $dailyLimit : PHP_INT_MAX;
        $effectiveHourlyLimit = $hourlyLimit > 0 ? $hourlyLimit : PHP_INT_MAX;

        return new ProviderStatus(
            key:                 $providerKey,
            driver:              $providerConfig['driver'] ?? 'unknown',
            enabled:             (bool) ($providerConfig['enabled'] ?? false),
            dailyLimit:          $dailyLimit,
            hourlyLimit:         $hourlyLimit,
            sentToday:           $stats['sent_today'],
            sentThisHour:        $stats['sent_this_hour'],
            sentTotal:           $stats['sent_total'],
            isCooling:           $isCooling,
            coolingUntil:        $coolingUntil ? Carbon::createFromTimestamp($coolingUntil)->toDateTimeString() : null,
            consecutiveFailures: $stats['consecutive_failures'],
            lastUsedAt:          $stats['last_used_at'] ? Carbon::createFromTimestamp($stats['last_used_at'])->toDateTimeString() : null,
            remainingToday:      $dailyLimit > 0 ? max(0, $dailyLimit - $stats['sent_today']) : PHP_INT_MAX,
            remainingThisHour:   $hourlyLimit > 0 ? max(0, $hourlyLimit - $stats['sent_this_hour']) : PHP_INT_MAX,
        );
    }

    public function resetProvider(string $providerKey): void
    {
        $this->storage->resetDaily($providerKey);
        $this->storage->resetHourly($providerKey);
        $this->storage->resetFailures($providerKey);
        $this->storage->clearCooling($providerKey);
    }

    public function getRemainingToday(string $providerKey): int
    {
        $limit = (int) ($this->config['providers'][$providerKey]['daily_limit'] ?? 0);
        if ($limit === 0) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - $this->storage->getSentToday($providerKey));
    }

    public function getRemainingThisHour(string $providerKey): int
    {
        $limit = (int) ($this->config['providers'][$providerKey]['hourly_limit'] ?? 0);
        if ($limit === 0) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - $this->storage->getSentThisHour($providerKey));
    }
}
