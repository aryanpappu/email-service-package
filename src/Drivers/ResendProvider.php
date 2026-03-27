<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Resend — 100 emails/day, 3,000/month free tier.
 * API docs: https://resend.com/docs/api-reference/emails/send-email
 */
class ResendProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.resend.com/emails';

    public function getDriver(): string
    {
        return 'resend';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');
            $from      = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;

            $payload = [
                'from'    => $from,
                'to'      => array_keys($message->to),
                'subject' => $message->subject,
            ];

            if ($message->htmlBody) {
                $payload['html'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $payload['text'] = $message->textBody;
            }

            if ($message->cc) {
                $payload['cc'] = array_keys($message->cc);
            }

            if ($message->bcc) {
                $payload['bcc'] = array_keys($message->bcc);
            }

            if ($message->replyTo) {
                $payload['reply_to'] = [$message->replyTo];
            }

            if ($message->attachments) {
                $payload['attachments'] = array_map(fn ($a) => [
                    'filename' => $a['name'],
                    'content'  => base64_encode(file_get_contents($a['path'])),
                ], $message->attachments);
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->requireConfig('api_key'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return SendResult::success($this->key, $body['id'] ?? null);
        } catch (GuzzleException $e) {
            $body = method_exists($e, 'getResponse') && $e->getResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();
            return SendResult::failure($this->key, $body);
        } catch (\Throwable $e) {
            return SendResult::failure($this->key, $e->getMessage());
        }
    }

    protected function validateConfig(): void
    {
        $this->requireConfig('api_key');
    }
}
