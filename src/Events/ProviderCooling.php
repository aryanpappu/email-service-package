<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Events;

class ProviderCooling
{
    public function __construct(public readonly string $providerKey) {}
}
