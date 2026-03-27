<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Strategies;

use TechSolutionStuff\SmartMailer\Contracts\RotationStrategy;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;

/**
 * Always pick the highest-priority provider that still has quota.
 * Priority 1 = highest. Falls back to priority 2, 3, etc.
 */
class PriorityStrategy implements RotationStrategy
{
    public function __construct(private readonly array $providerConfigs) {}

    public function select(array $availableProviderKeys, UsageTracker $tracker): ?string
    {
        if (empty($availableProviderKeys)) {
            return null;
        }

        usort($availableProviderKeys, function (string $a, string $b): int {
            $priorityA = (int) ($this->providerConfigs[$a]['priority'] ?? 99);
            $priorityB = (int) ($this->providerConfigs[$b]['priority'] ?? 99);
            return $priorityA <=> $priorityB;
        });

        return $availableProviderKeys[0];
    }

    public function getName(): string
    {
        return 'priority';
    }
}
