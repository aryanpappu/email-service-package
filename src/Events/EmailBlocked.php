<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Events;

class EmailBlocked
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $reason,
    ) {}
}
