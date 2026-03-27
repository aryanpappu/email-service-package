<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Netcore Email API (Pepipost) — 100 emails/day free tier.
 * API docs: https://docs.netcorecloud.com/
 */
class NetcoreProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://emailapi.netcorecloud.net/v5.1/mail/send';

    public function getDriver(): string
    {
        return 'netcore';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');

            $personalizations = [[
                'to' => array_map(
                    fn ($name, $email) => ['name' => $name ?: $email, 'email' => $email],
                    $message->to,
                    array_keys($message->to),
                ),
            ]];

            $payload = [
                'from'             => ['email' => $fromEmail, 'name' => $fromName],
                'subject'          => $message->subject,
                'personalizations' => $personalizations,
                'content'          => [],
            ];

            if ($message->textBody) {
                $payload['content'][] = ['type' => 'text', 'value' => $message->textBody];
            }

            if ($message->htmlBody) {
                $payload['content'][] = ['type' => 'html', 'value' => $message->htmlBody];
            }

            if ($message->replyTo) {
                $payload['reply_to'] = $message->replyTo;
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'api_key'      => $this->requireConfig('api_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (($body['status'] ?? '') === 'success') {
                return SendResult::success($this->key, $body['data']['message_id'] ?? null);
            }

            return SendResult::failure($this->key, $body['message'] ?? 'Unknown Netcore error');
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
