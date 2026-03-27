<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Strategies;

use TechSolutionStuff\SmartMailer\Contracts\RotationStrategy;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;

/**
 * Randomly selects a provider, weighted by remaining daily quota.
 * Provides natural load distribution proportional to available capacity.
 */
class RandomWeightedStrategy implements RotationStrategy
{
    public function select(array $availableProviderKeys, UsageTracker $tracker): ?string
    {
        if (empty($availableProviderKeys)) {
            return null;
        }

        $weights = [];
        $total   = 0;

        foreach ($availableProviderKeys as $key) {
            $remaining = max(1, $tracker->getRemainingToday($key)); // min weight = 1
            $weights[$key] = $remaining;
            $total        += $remaining;
        }

        $random = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    public function getName(): string
    {
        return 'random_weighted';
    }
}
