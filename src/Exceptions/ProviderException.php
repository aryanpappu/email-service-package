<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Exceptions;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(string $providerKey, string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Provider [{$providerKey}] failed: {$message}", 0, $previous);
    }
}
