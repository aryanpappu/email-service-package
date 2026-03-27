<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Outlook / Hotmail SMTP — ~300 emails/day.
 * Works with personal @outlook.com or @hotmail.com accounts.
 */
class OutlookSmtpProvider extends SmtpProvider
{
    public function getDriver(): string
    {
        return 'outlook_smtp';
    }

    public function send(MailMessage $message): SendResult
    {
        // Build transport directly with fixed Outlook settings — does NOT mutate readonly $this->config
        try {
            $transport = new EsmtpTransport('smtp-mail.outlook.com', 587, false);
            $transport->setUsername($this->requireConfig('username'));
            $transport->setPassword($this->requireConfig('password'));

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);
            $email  = $this->buildEmail($message);
            $mailer->send($email);

            return SendResult::success($this->key);
        } catch (\Throwable $e) {
            return SendResult::failure($this->key, $e->getMessage());
        }
    }

    protected function validateConfig(): void
    {
        $this->requireConfig('username');
        $this->requireConfig('password');
    }
}
