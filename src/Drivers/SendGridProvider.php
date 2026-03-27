<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * SendGrid — 100 emails/day free tier.
 * API docs: https://docs.sendgrid.com/api-reference/mail-send/mail-send
 */
class SendGridProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    public function getDriver(): string
    {
        return 'sendgrid';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $toPersonalizations = [['to' => $this->buildRecipients($message->to)]];

            if ($message->cc) {
                $toPersonalizations[0]['cc'] = $this->buildRecipients($message->cc);
            }

            if ($message->bcc) {
                $toPersonalizations[0]['bcc'] = $this->buildRecipients($message->bcc);
            }

            $payload = [
                'personalizations' => $toPersonalizations,
                'from'             => [
                    'email' => $message->fromEmail ?: $this->requireConfig('from_email'),
                    'name'  => $message->fromName ?: ($this->getFromName() ?? ''),
                ],
                'subject'          => $message->subject,
                'content'          => [],
            ];

            if ($message->textBody) {
                $payload['content'][] = ['type' => 'text/plain', 'value' => $message->textBody];
            }

            if ($message->htmlBody) {
                $payload['content'][] = ['type' => 'text/html', 'value' => $message->htmlBody];
            }

            if (empty($payload['content'])) {
                $payload['content'][] = ['type' => 'text/plain', 'value' => ' '];
            }

            if ($message->replyTo) {
                $payload['reply_to'] = ['email' => $message->replyTo, 'name' => $message->replyToName ?? ''];
            }

            if ($message->attachments) {
                $payload['attachments'] = array_map(fn ($a) => [
                    'content'  => base64_encode(file_get_contents($a['path'])),
                    'filename' => $a['name'],
                    'type'     => $a['mime'],
                ], $message->attachments);
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->requireConfig('api_key'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $messageId = $response->getHeaderLine('X-Message-Id');

            return SendResult::success($this->key, $messageId ?: null);
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

    private function buildRecipients(array $recipients): array
    {
        return array_map(
            fn ($name, $email) => array_filter(['email' => $email, 'name' => $name ?: null]),
            $recipients,
            array_keys($recipients),
        );
    }
}
