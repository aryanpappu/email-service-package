<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Exceptions;

use RuntimeException;

class SpamBlockedException extends RuntimeException
{
    public function __construct(string $recipient, string $reason)
    {
        parent::__construct("Email to [{$recipient}] blocked: {$reason}");
    }
}
