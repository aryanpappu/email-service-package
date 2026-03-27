<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Strategies;

use TechSolutionStuff\SmartMailer\Contracts\RotationStrategy;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;

/**
 * Always picks the provider with the most remaining daily quota.
 * Best for maximizing utilization of free tiers.
 */
class LeastUsedStrategy implements RotationStrategy
{
    public function select(array $availableProviderKeys, UsageTracker $tracker): ?string
    {
        if (empty($availableProviderKeys)) {
            return null;
        }

        $best          = null;
        $bestRemaining = -1;

        foreach ($availableProviderKeys as $key) {
            $remaining = $tracker->getRemainingToday($key);
            if ($remaining > $bestRemaining) {
                $bestRemaining = $remaining;
                $best          = $key;
            }
        }

        return $best;
    }

    public function getName(): string
    {
        return 'least_used';
    }
}
