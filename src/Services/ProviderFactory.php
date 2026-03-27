<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Services;

use TechSolutionStuff\SmartMailer\Contracts\EmailProvider;
use TechSolutionStuff\SmartMailer\Drivers\AmazonSesProvider;
use TechSolutionStuff\SmartMailer\Drivers\BrevoProvider;
use TechSolutionStuff\SmartMailer\Drivers\CustomSmtpProvider;
use TechSolutionStuff\SmartMailer\Drivers\ElasticEmailProvider;
use TechSolutionStuff\SmartMailer\Drivers\GmailSmtpProvider;
use TechSolutionStuff\SmartMailer\Drivers\MailchimpTransactionalProvider;
use TechSolutionStuff\SmartMailer\Drivers\MailgunProvider;
use TechSolutionStuff\SmartMailer\Drivers\MailjetProvider;
use TechSolutionStuff\SmartMailer\Drivers\NetcoreProvider;
use TechSolutionStuff\SmartMailer\Drivers\OutlookSmtpProvider;
use TechSolutionStuff\SmartMailer\Drivers\Smtp2GoProvider;
use TechSolutionStuff\SmartMailer\Drivers\PostmarkProvider;
use TechSolutionStuff\SmartMailer\Drivers\ResendProvider;
use TechSolutionStuff\SmartMailer\Drivers\SendGridProvider;
use TechSolutionStuff\SmartMailer\Drivers\SendpulseProvider;
use TechSolutionStuff\SmartMailer\Drivers\SmtpProvider;
use TechSolutionStuff\SmartMailer\Drivers\SparkPostProvider;
use TechSolutionStuff\SmartMailer\Drivers\ZeptoMailProvider;

class ProviderFactory
{
    /** @var array<string, class-string<EmailProvider>> */
    private array $drivers = [
        'smtp'        => SmtpProvider::class,
        'brevo'       => BrevoProvider::class,
        'sendinblue'  => BrevoProvider::class, // alias
        'sendgrid'    => SendGridProvider::class,
        'mailgun'     => MailgunProvider::class,
        'resend'      => ResendProvider::class,
        'mailjet'     => MailjetProvider::class,
        'smtp2go'     => Smtp2GoProvider::class,
        'elasticemail' => ElasticEmailProvider::class,
        'ses'         => AmazonSesProvider::class,
        'amazonses'   => AmazonSesProvider::class, // alias
        'sendpulse'   => SendpulseProvider::class,
        'postmark'    => PostmarkProvider::class,
        'sparkpost'   => SparkPostProvider::class,
        'mandrill'    => MailchimpTransactionalProvider::class,
        'zeptomail'   => ZeptoMailProvider::class,
        'netcore'     => NetcoreProvider::class,
        'pepipost'    => NetcoreProvider::class, // alias
        'gmail_smtp'  => GmailSmtpProvider::class,
        'outlook_smtp' => OutlookSmtpProvider::class,
        'custom_smtp' => CustomSmtpProvider::class,
    ];

    /** @var array<string, EmailProvider> */
    private array $resolved = [];

    public function __construct(private readonly array $config) {}

    public function make(string $providerKey): EmailProvider
    {
        if (isset($this->resolved[$providerKey])) {
            return $this->resolved[$providerKey];
        }

        $providerConfig = $this->config['providers'][$providerKey]
            ?? throw new \InvalidArgumentException("Provider [{$providerKey}] not found in config.");

        $driver = $providerConfig['driver']
            ?? throw new \InvalidArgumentException("Provider [{$providerKey}] missing 'driver' key.");

        $driverClass = $this->drivers[$driver]
            ?? throw new \InvalidArgumentException("Unknown driver [{$driver}] for provider [{$providerKey}].");

        $this->resolved[$providerKey] = new $driverClass($providerKey, $providerConfig);

        return $this->resolved[$providerKey];
    }

    /**
     * Register a custom driver class.
     *
     * @param class-string<EmailProvider> $class
     */
    public function extend(string $driverName, string $class): void
    {
        $this->drivers[$driverName] = $class;
    }

    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }
}
