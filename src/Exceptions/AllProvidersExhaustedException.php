<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Exceptions;

use RuntimeException;

class AllProvidersExhaustedException extends RuntimeException
{
    public function __construct(string $domain = 'default')
    {
        parent::__construct(
            "All email providers exhausted for domain [{$domain}]. Daily/hourly limits reached or all providers cooling."
        );
    }
}
