<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

/**
 * Fully configurable generic SMTP provider.
 * Use this for any SMTP server: Zoho, Yahoo, custom mail servers, etc.
 * All connection params come from config.
 */
class CustomSmtpProvider extends SmtpProvider
{
    public function getDriver(): string
    {
        return 'custom_smtp';
    }

    protected function validateConfig(): void
    {
        $this->requireConfig('host');
        $this->requireConfig('username');
        $this->requireConfig('password');
    }
}
