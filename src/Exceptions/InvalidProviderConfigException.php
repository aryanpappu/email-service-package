<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Exceptions;

use InvalidArgumentException;

class InvalidProviderConfigException extends InvalidArgumentException
{
    public function __construct(string $providerKey, string $field)
    {
        parent::__construct("Provider [{$providerKey}] missing required config field: [{$field}]");
    }
}
