<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Events;

class AllProvidersExhausted
{
    public function __construct(public readonly string $domain) {}
}
