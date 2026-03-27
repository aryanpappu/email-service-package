<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Events;

use TechSolutionStuff\SmartMailer\DTOs\MailMessage;

class EmailSent
{
    public function __construct(
        public readonly string $providerKey,
        public readonly MailMessage $message,
        public readonly ?string $messageId = null,
    ) {}
}
