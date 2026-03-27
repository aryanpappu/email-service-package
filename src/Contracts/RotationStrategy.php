<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Contracts;

use TechSolutionStuff\SmartMailer\Services\UsageTracker;

interface RotationStrategy
{
    /**
     * Select the best provider key from available candidates.
     *
     * @param  array<string>  $availableProviderKeys
     */
    public function select(array $availableProviderKeys, UsageTracker $tracker): ?string;

    public function getName(): string;
}
