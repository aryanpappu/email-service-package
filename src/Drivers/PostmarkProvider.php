<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Postmark — paid but very affordable and reliable.
 * API docs: https://postmarkapp.com/developer/api/email-api
 */
class PostmarkProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.postmarkapp.com/email';

    public function getDriver(): string
    {
        return 'postmark';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');
            $from      = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;

            $payload = [
                'From'     => $from,
                'To'       => $this->buildAddressString($message->to),
                'Subject'  => $message->subject,
            ];

            if ($message->htmlBody) {
                $payload['HtmlBody'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $payload['TextBody'] = $message->textBody;
            }

            if ($message->cc) {
                $payload['Cc'] = $this->buildAddressString($message->cc);
            }

            if ($message->bcc) {
                $payload['Bcc'] = $this->buildAddressString($message->bcc);
            }

            if ($message->replyTo) {
                $payload['ReplyTo'] = $message->replyTo;
            }

            if ($message->attachments) {
                $payload['Attachments'] = array_map(fn ($a) => [
                    'Name'        => $a['name'],
                    'Content'     => base64_encode(file_get_contents($a['path'])),
                    'ContentType' => $a['mime'],
                ], $message->attachments);
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Accept'                  => 'application/json',
                    'Content-Type'            => 'application/json',
                    'X-Postmark-Server-Token' => $this->requireConfig('server_token'),
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (($body['ErrorCode'] ?? 0) !== 0) {
                return SendResult::failure($this->key, $body['Message'] ?? 'Postmark error');
            }

            return SendResult::success($this->key, $body['MessageID'] ?? null);
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
        $this->requireConfig('server_token');
    }

    private function buildAddressString(array $recipients): string
    {
        return implode(', ', array_map(
            fn ($name, $email) => $name ? "{$name} <{$email}>" : $email,
            $recipients,
            array_keys($recipients),
        ));
    }
}
