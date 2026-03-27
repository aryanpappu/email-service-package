<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Contracts;

use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

interface EmailProvider
{
    public function send(MailMessage $message): SendResult;

    public function getKey(): string;

    public function getDriver(): string;

    public function getDailyLimit(): int;

    public function getHourlyLimit(): int;

    public function getPriority(): int;

    public function isEnabled(): bool;

    public function getFromEmail(): ?string;

    public function getFromName(): ?string;
}
