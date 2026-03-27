<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Events;

class ProviderSwitched
{
    public function __construct(
        public readonly string $fromProvider,
        public readonly string $toProvider,
        public readonly string $reason,
    ) {}
}
