<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Console;

use Illuminate\Console\Command;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;

class TestCommand extends Command
{
    protected $signature = 'smart-mailer:test
        {email : Recipient email address}
        {--provider= : Force a specific provider}
        {--domain= : Use domain-specific provider pool}
        {--subject=SmartMailer Test : Email subject}';

    protected $description = 'Send a test email through the SmartMailer provider pool';

    public function handle(ProviderPool $pool): int
    {
        $recipient = $this->argument('email');
        $domain    = $this->option('domain');
        $subject   = $this->option('subject');

        // SEC-4: Validate email address before use
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: [{$recipient}]");
            return self::FAILURE;
        }

        $this->info("Sending test email to [{$recipient}]...");

        $message = new MailMessage(
            fromEmail: config('smart-mailer.domains.default.from_email', config('mail.from.address', 'test@example.com')),
            fromName:  config('smart-mailer.domains.default.from_name', config('mail.from.name', 'SmartMailer Test')),
            subject:   $subject,
            htmlBody:  $this->buildHtmlBody(),
            textBody:  'This is a SmartMailer test email. If you received this, the package is working correctly.',
            domain:    $domain,
        );

        $message->to($recipient);

        try {
            $result = $pool->send($message);

            $this->info('');
            $this->info('  <fg=green>✓ Email sent successfully!</>');
            $this->line("  Provider: <fg=cyan>{$result->providerKey}</>");
            if ($result->messageId) {
                $this->line("  Message ID: <fg=gray>{$result->messageId}</>");
            }
            $this->info('');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send test email: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function buildHtmlBody(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px;">
            <div style="background: #4F46E5; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; font-size: 24px;">SmartMailer Test</h1>
            </div>
            <div style="border: 1px solid #e5e7eb; border-top: none; padding: 20px; border-radius: 0 0 8px 8px;">
                <p>This is a test email sent by the <strong>SmartMailer</strong> Laravel package.</p>
                <p>If you received this email, your configuration is working correctly.</p>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                <p style="color: #6b7280; font-size: 12px;">
                    Sent via SmartMailer &mdash; Multi-provider email rotation for Laravel
                </p>
            </div>
        </body>
        </html>
        HTML;
    }
}
