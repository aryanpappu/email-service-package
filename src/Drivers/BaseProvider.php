<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use TechSolutionStuff\SmartMailer\Contracts\EmailProvider;
use TechSolutionStuff\SmartMailer\Exceptions\InvalidProviderConfigException;

abstract class BaseProvider implements EmailProvider
{
    public function __construct(
        protected readonly string $key,
        protected readonly array $config,
    ) {
        $this->validateConfig();
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDailyLimit(): int
    {
        return (int) ($this->config['daily_limit'] ?? PHP_INT_MAX);
    }

    public function getHourlyLimit(): int
    {
        return (int) ($this->config['hourly_limit'] ?? PHP_INT_MAX);
    }

    public function getPriority(): int
    {
        return (int) ($this->config['priority'] ?? 99);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function getFromEmail(): ?string
    {
        return $this->config['from_email'] ?? null;
    }

    public function getFromName(): ?string
    {
        return $this->config['from_name'] ?? null;
    }

    protected function requireConfig(string $field): string
    {
        if (empty($this->config[$field])) {
            throw new InvalidProviderConfigException($this->key, $field);
        }

        return (string) $this->config[$field];
    }

    protected function getConfig(string $field, mixed $default = null): mixed
    {
        return $this->config[$field] ?? $default;
    }

    /**
     * Override in subclasses to validate driver-specific required fields.
     */
    protected function validateConfig(): void {}
}
