<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * SMTP2GO — 1,000 emails/month free tier.
 * API docs: https://apidoc.smtp2go.com/documentation/#/POST%20/email/send
 */
class Smtp2GoProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.smtp2go.com/v3/email/send';

    public function getDriver(): string
    {
        return 'smtp2go';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');
            $from      = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;

            $payload = [
                'api_key'  => $this->requireConfig('api_key'),
                'to'       => $this->buildAddressList($message->to),
                'sender'   => $from,
                'subject'  => $message->subject,
            ];

            if ($message->htmlBody) {
                $payload['html_body'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $payload['text_body'] = $message->textBody;
            }

            if ($message->cc) {
                $payload['cc'] = $this->buildAddressList($message->cc);
            }

            if ($message->bcc) {
                $payload['bcc'] = $this->buildAddressList($message->bcc);
            }

            if ($message->attachments) {
                $payload['attachments'] = array_map(fn ($a) => [
                    'filename'  => $a['name'],
                    'fileblob'  => base64_encode(file_get_contents($a['path'])),
                    'mimetype'  => $a['mime'],
                ], $message->attachments);
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (!($body['data']['succeeded'] ?? false)) {
                return SendResult::failure($this->key, $body['data']['error'] ?? 'Unknown error');
            }

            return SendResult::success($this->key, $body['data']['email_id'] ?? null);
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

    private function buildAddressList(array $recipients): array
    {
        return array_map(
            fn ($name, $email) => $name ? "{$name} <{$email}>" : $email,
            $recipients,
            array_keys($recipients),
        );
    }
}
