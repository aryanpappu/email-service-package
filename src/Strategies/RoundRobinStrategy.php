<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Strategies;

use Illuminate\Cache\Repository as Cache;
use TechSolutionStuff\SmartMailer\Contracts\RotationStrategy;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;

/**
 * Cycles through providers in order, regardless of usage.
 * Useful for spreading load evenly when all limits are generous.
 */
class RoundRobinStrategy implements RotationStrategy
{
    public function __construct(private readonly Cache $cache) {}

    public function select(array $availableProviderKeys, UsageTracker $tracker): ?string
    {
        if (empty($availableProviderKeys)) {
            return null;
        }

        sort($availableProviderKeys); // Consistent ordering

        $poolKey = 'smart_mailer:rr_index:' . md5(implode(',', $availableProviderKeys));
        $index   = (int) $this->cache->get($poolKey, 0);

        $selected = $availableProviderKeys[$index % count($availableProviderKeys)];
        $this->cache->put($poolKey, ($index + 1) % count($availableProviderKeys), 86400);

        return $selected;
    }

    public function getName(): string
    {
        return 'round_robin';
    }
}
