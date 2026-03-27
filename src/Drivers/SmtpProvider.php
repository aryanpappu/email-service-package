<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

class SmtpProvider extends BaseProvider
{
    public function getDriver(): string
    {
        return 'smtp';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $transport = new EsmtpTransport(
                $this->requireConfig('host'),
                (int) $this->getConfig('port', 587),
                $this->getConfig('encryption', 'tls') === 'ssl',
            );

            $transport->setUsername($this->requireConfig('username'));
            $transport->setPassword($this->requireConfig('password'));

            $mailer = new Mailer($transport);
            $email  = $this->buildEmail($message);
            $mailer->send($email);

            return SendResult::success($this->key);
        } catch (\Throwable $e) {
            return SendResult::failure($this->key, $e->getMessage());
        }
    }

    protected function validateConfig(): void
    {
        $this->requireConfig('host');
        $this->requireConfig('username');
        $this->requireConfig('password');
    }

    protected function buildEmail(MailMessage $message): Email
    {
        $fromEmail = $message->fromEmail ?: $this->requireConfig('username');
        $fromName  = $message->fromName ?: ($this->getFromName() ?? '');

        $email = (new Email())
            ->from(new Address($fromEmail, $fromName))
            ->subject($message->subject);

        foreach ($message->to as $toEmail => $toName) {
            $email->addTo(new Address($toEmail, $toName));
        }

        foreach ($message->cc as $ccEmail => $ccName) {
            $email->addCc(new Address($ccEmail, $ccName));
        }

        foreach ($message->bcc as $bccEmail => $bccName) {
            $email->addBcc(new Address($bccEmail, $bccName));
        }

        if ($message->replyTo) {
            $email->replyTo(new Address($message->replyTo, $message->replyToName ?? ''));
        }

        if ($message->htmlBody) {
            $email->html($message->htmlBody);
        }

        if ($message->textBody) {
            $email->text($message->textBody);
        }

        foreach ($message->headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        foreach ($message->attachments as $attachment) {
            $email->attachFromPath($attachment['path'], $attachment['name'], $attachment['mime']);
        }

        return $email;
    }
}
