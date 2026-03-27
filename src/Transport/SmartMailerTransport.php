<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage as SmartMailMessage;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;

/**
 * Symfony Mailer transport bridge.
 * Allows SmartMailer to work transparently with Laravel's Mail facade.
 */
class SmartMailerTransport extends AbstractTransport
{
    public function __construct(
        private readonly ProviderPool $pool,
        private readonly ?string $domain = null,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        $email    = MessageConverter::toEmail($original);

        $fromAddresses = $email->getFrom();
        $fromAddress   = $fromAddresses[0] ?? null;

        $smartMessage = new SmartMailMessage(
            fromEmail: $fromAddress?->getAddress() ?? '',
            fromName:  $fromAddress?->getName() ?? '',
            subject:   $email->getSubject() ?? '',
            htmlBody:  $email->getHtmlBody(),
            textBody:  $email->getTextBody(),
            domain:    $this->domain,
        );

        foreach ($email->getTo() as $address) {
            $smartMessage->to($address->getAddress(), $address->getName());
        }

        foreach ($email->getCc() as $address) {
            $smartMessage->cc($address->getAddress(), $address->getName());
        }

        foreach ($email->getBcc() as $address) {
            $smartMessage->bcc($address->getAddress(), $address->getName());
        }

        $replyTo = $email->getReplyTo();
        if (!empty($replyTo)) {
            $smartMessage->replyTo($replyTo[0]->getAddress(), $replyTo[0]->getName());
        }

        // MEDIUM FIX: Remove post-send header mutation (dead code — has no effect on delivered email)
        $this->pool->send($smartMessage);
    }

    public function __toString(): string
    {
        return 'smart';
    }
}
