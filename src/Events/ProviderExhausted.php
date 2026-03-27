<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Events;

class ProviderExhausted
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $limitType, // 'daily' | 'hourly' | 'daily_or_hourly'
    ) {}
}
