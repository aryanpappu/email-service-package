<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\DTOs;

class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $providerKey,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly array $debug = [],
    ) {}

    public static function success(string $providerKey, ?string $messageId = null, array $debug = []): self
    {
        return new self(true, $providerKey, $messageId, null, $debug);
    }

    public static function failure(string $providerKey, string $error, array $debug = []): self
    {
        return new self(false, $providerKey, null, $error, $debug);
    }
}
