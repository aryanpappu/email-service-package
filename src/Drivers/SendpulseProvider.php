<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * SendPulse — 15,000 emails/month free tier.
 * API docs: https://sendpulse.com/integrations/api/smtp
 */
class SendpulseProvider extends BaseProvider
{
    private const TOKEN_ENDPOINT = 'https://api.sendpulse.com/oauth/access_token';
    private const SEND_ENDPOINT  = 'https://api.sendpulse.com/smtp/emails';

    public function getDriver(): string
    {
        return 'sendpulse';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $token = $this->getAccessToken();

            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');

            $payload = [
                'email' => [
                    'html'    => $message->htmlBody ?? '',
                    'text'    => $message->textBody ?? '',
                    'subject' => $message->subject,
                    'from'    => ['name' => $fromName, 'email' => $fromEmail],
                    'to'      => $this->buildRecipients($message->to),
                ],
            ];

            if ($message->cc) {
                $payload['email']['cc'] = $this->buildRecipients($message->cc);
            }

            if ($message->bcc) {
                $payload['email']['bcc'] = $this->buildRecipients($message->bcc);
            }

            $response = $client->post(self::SEND_ENDPOINT, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (($body['result'] ?? false) === false) {
                return SendResult::failure($this->key, $body['message'] ?? 'Unknown SendPulse error');
            }

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
        $this->requireConfig('client_id');
        $this->requireConfig('client_secret');
    }

    private function getAccessToken(): string
    {
        $client   = new Client(['timeout' => 10]);
        $response = $client->post(self::TOKEN_ENDPOINT, [
            'json' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->requireConfig('client_id'),
                'client_secret' => $this->requireConfig('client_secret'),
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);

        return $body['access_token'] ?? throw new \RuntimeException('Failed to get SendPulse access token');
    }

    private function buildRecipients(array $recipients): array
    {
        return array_map(
            fn ($name, $email) => ['name' => $name ?: $email, 'email' => $email],
            $recipients,
            array_keys($recipients),
        );
    }
}
