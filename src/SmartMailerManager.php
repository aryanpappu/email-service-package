<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer;

use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;
use TechSolutionStuff\SmartMailer\Services\ProviderFactory;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;

/**
 * Fluent builder for sending emails directly without Laravel's Mail system.
 * Useful for non-Mailable sends, notifications, or API-driven usage.
 */
class SmartMailerManager
{
    private ?string $domain    = null;
    private ?string $toEmail   = null;
    private string  $toName    = '';
    private ?string $fromEmail = null;
    private string  $fromName  = '';
    private ?string $subject   = null;
    private ?string $html      = null;
    private ?string $text      = null;

    public function __construct(
        private readonly ProviderPool $pool,
        private readonly ProviderFactory $factory,
        private readonly array $config,
    ) {}

    public function domain(string $domain): static
    {
        $clone = clone $this;
        $clone->domain = $domain;
        return $clone;
    }

    public function to(string $email, string $name = ''): static
    {
        $clone = clone $this;
        $clone->toEmail = $email;
        $clone->toName  = $name;
        return $clone;
    }

    public function from(string $email, string $name = ''): static
    {
        $clone = clone $this;
        $clone->fromEmail = $email;
        $clone->fromName  = $name;
        return $clone;
    }

    public function subject(string $subject): static
    {
        $clone = clone $this;
        $clone->subject = $subject;
        return $clone;
    }

    public function html(string $html): static
    {
        $clone = clone $this;
        $clone->html = $html;
        return $clone;
    }

    public function text(string $text): static
    {
        $clone = clone $this;
        $clone->text = $text;
        return $clone;
    }

    public function send(?MailMessage $message = null): SendResult
    {
        if ($message === null) {
            // HIGH FIX: Validate fromEmail before constructing message
            if (empty($this->fromEmail) && empty(config('smart-mailer.domains.' . ($this->domain ?? 'default') . '.from_email'))) {
                throw new \InvalidArgumentException('Sender email (from) is required. Call ->from() or set from_email in domain config.');
            }

            if (empty($this->toEmail)) {
                throw new \InvalidArgumentException('Recipient email (to) is required. Call ->to().');
            }

            if (empty($this->subject)) {
                throw new \InvalidArgumentException('Email subject is required. Call ->subject().');
            }

            $message = new MailMessage(
                fromEmail: $this->fromEmail ?? '',
                fromName:  $this->fromName,
                subject:   $this->subject,
                htmlBody:  $this->html,
                textBody:  $this->text,
                domain:    $this->domain,
            );

            $message->to($this->toEmail, $this->toName);
        }

        if ($this->domain && !$message->domain) {
            $message->domain = $this->domain;
        }

        return $this->pool->send($message);
    }

    /** @return \TechSolutionStuff\SmartMailer\DTOs\ProviderStatus[] */
    public function status(): array
    {
        return $this->pool->getAllStatuses();
    }

    public function reset(string $providerKey): void
    {
        $this->pool->resetProvider($providerKey);
    }

    /**
     * Register a custom provider driver.
     *
     * @param class-string<\TechSolutionStuff\SmartMailer\Contracts\EmailProvider> $class
     */
    public function extend(string $driver, string $class): void
    {
        $this->factory->extend($driver, $class);
    }

    public function getPool(): ProviderPool
    {
        return $this->pool;
    }
}
