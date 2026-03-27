<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\DTOs;

class ProviderStatus
{
    public function __construct(
        public readonly string $key,
        public readonly string $driver,
        public readonly bool $enabled,
        public readonly int $dailyLimit,
        public readonly int $hourlyLimit,
        public readonly int $sentToday,
        public readonly int $sentThisHour,
        public readonly int $sentTotal,
        public readonly bool $isCooling,
        public readonly ?string $coolingUntil,
        public readonly int $consecutiveFailures,
        public readonly ?string $lastUsedAt,
        public readonly int $remainingToday,
        public readonly int $remainingThisHour,
    ) {}
}
