<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\DTOs;

class MailMessage
{
    /** @var array<string, string> */
    public array $to = [];

    /** @var array<string, string> */
    public array $cc = [];

    /** @var array<string, string> */
    public array $bcc = [];

    /** @var array<int, array{path: string, name: string, mime: string}> */
    public array $attachments = [];

    /** @var array<string, string> */
    public array $headers = [];

    public ?string $replyTo = null;
    public ?string $replyToName = null;

    public function __construct(
        public string $fromEmail,
        public string $fromName,
        public string $subject,
        public ?string $htmlBody = null,
        public ?string $textBody = null,
        public ?string $domain = null,
    ) {}

    public static function make(
        string $fromEmail,
        string $fromName,
        string $subject,
        ?string $htmlBody = null,
        ?string $textBody = null,
    ): self {
        return new self($fromEmail, $fromName, $subject, $htmlBody, $textBody);
    }

    public function to(string $email, string $name = ''): self
    {
        $this->to[$email] = $name;
        return $this;
    }

    public function cc(string $email, string $name = ''): self
    {
        $this->cc[$email] = $name;
        return $this;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $this->bcc[$email] = $name;
        return $this;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $this->replyTo = $email;
        $this->replyToName = $name;
        return $this;
    }

    public function attach(string $path, string $name = '', string $mime = ''): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name ?: basename($path),
            'mime' => $mime ?: 'application/octet-stream',
        ];
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /** @return string[] */
    public function getRecipientEmails(): array
    {
        return array_keys($this->to);
    }

    public function getPrimaryRecipient(): string
    {
        return array_key_first($this->to) ?? '';
    }
}
