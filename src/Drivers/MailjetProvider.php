<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Mailjet — 200 emails/day, 6,000/month free tier.
 * API docs: https://dev.mailjet.com/email/reference/send-emails/
 */
class MailjetProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.mailjet.com/v3.1/send';

    public function getDriver(): string
    {
        return 'mailjet';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $msgPayload = [
                'From'     => [
                    'Email' => $message->fromEmail ?: $this->requireConfig('from_email'),
                    'Name'  => $message->fromName ?: ($this->getFromName() ?? ''),
                ],
                'To'       => $this->buildRecipients($message->to),
                'Subject'  => $message->subject,
            ];

            if ($message->htmlBody) {
                $msgPayload['HTMLPart'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $msgPayload['TextPart'] = $message->textBody;
            }

            if ($message->cc) {
                $msgPayload['Cc'] = $this->buildRecipients($message->cc);
            }

            if ($message->bcc) {
                $msgPayload['Bcc'] = $this->buildRecipients($message->bcc);
            }

            if ($message->replyTo) {
                $msgPayload['ReplyTo'] = ['Email' => $message->replyTo, 'Name' => $message->replyToName ?? ''];
            }

            if ($message->attachments) {
                $msgPayload['Attachments'] = array_map(fn ($a) => [
                    'ContentType' => $a['mime'],
                    'Filename'    => $a['name'],
                    'Base64Content' => base64_encode(file_get_contents($a['path'])),
                ], $message->attachments);
            }

            $response = $client->post(self::API_ENDPOINT, [
                'auth'    => [$this->requireConfig('api_key'), $this->requireConfig('api_secret')],
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => ['Messages' => [$msgPayload]],
            ]);

            $body      = json_decode((string) $response->getBody(), true);
            $messageId = $body['Messages'][0]['To'][0]['MessageID'] ?? null;

            return SendResult::success($this->key, $messageId ? (string) $messageId : null);
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
        $this->requireConfig('api_secret');
    }

    private function buildRecipients(array $recipients): array
    {
        return array_map(
            fn ($name, $email) => ['Email' => $email, 'Name' => $name ?: ''],
            $recipients,
            array_keys($recipients),
        );
    }
}
