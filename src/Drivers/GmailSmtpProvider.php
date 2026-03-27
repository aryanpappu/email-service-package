<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Gmail SMTP — 500 emails/day using App Password.
 * Requires 2FA enabled and an App Password generated in Google Account settings.
 */
class GmailSmtpProvider extends SmtpProvider
{
    public function getDriver(): string
    {
        return 'gmail_smtp';
    }

    public function send(MailMessage $message): SendResult
    {
        // Build transport directly with fixed Gmail settings — does NOT mutate readonly $this->config
        try {
            $transport = new EsmtpTransport('smtp.gmail.com', 587, false);
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
        $this->requireConfig('password'); // App Password
    }
}
