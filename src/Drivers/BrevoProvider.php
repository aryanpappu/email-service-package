<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Brevo (formerly Sendinblue) — 300 emails/day free tier.
 * API docs: https://developers.brevo.com/reference/sendtransacemail
 */
class BrevoProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function getDriver(): string
    {
        return 'brevo';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $payload = [
                'sender'  => [
                    'email' => $message->fromEmail ?: $this->requireConfig('from_email'),
                    'name'  => $message->fromName ?: ($this->getFromName() ?? ''),
                ],
                'to'      => $this->buildRecipients($message->to),
                'subject' => $message->subject,
            ];

            if ($message->htmlBody) {
                $payload['htmlContent'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $payload['textContent'] = $message->textBody;
            }

            if ($message->cc) {
                $payload['cc'] = $this->buildRecipients($message->cc);
            }

            if ($message->bcc) {
                $payload['bcc'] = $this->buildRecipients($message->bcc);
            }

            if ($message->replyTo) {
                $payload['replyTo'] = ['email' => $message->replyTo, 'name' => $message->replyToName ?? ''];
            }

            // SEC-5: Use base64-encoded content instead of URL fetch to prevent SSRF
            if ($message->attachments) {
                $payload['attachment'] = array_map(fn ($a) => [
                    'content' => base64_encode(file_get_contents($a['path'])),
                    'name'    => $a['name'],
                ], $message->attachments);
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'api-key'      => $this->requireConfig('api_key'),
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return SendResult::success($this->key, $body['messageId'] ?? null);
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

    /**
     * Build recipient array for Brevo API.
     * HIGH FIX: Explicit key order — email keys, name values — no ambiguous argument order.
     */
    private function buildRecipients(array $recipients): array
    {
        $result = [];
        foreach ($recipients as $email => $name) {
            $result[] = ['email' => $email, 'name' => $name ?: ''];
        }
        return $result;
    }
}
