<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Mailchimp Transactional (Mandrill) — paid, but very reliable.
 * API docs: https://mailchimp.com/developer/transactional/api/messages/
 */
class MailchimpTransactionalProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://mandrillapp.com/api/1.0/messages/send';

    public function getDriver(): string
    {
        return 'mandrill';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');

            $toList = [];
            foreach ($message->to as $email => $name) {
                $toList[] = ['email' => $email, 'name' => $name ?: $email, 'type' => 'to'];
            }
            foreach ($message->cc as $email => $name) {
                $toList[] = ['email' => $email, 'name' => $name ?: $email, 'type' => 'cc'];
            }
            foreach ($message->bcc as $email => $name) {
                $toList[] = ['email' => $email, 'name' => $name ?: $email, 'type' => 'bcc'];
            }

            $msgPayload = [
                'html'       => $message->htmlBody ?? '',
                'text'       => $message->textBody ?? '',
                'subject'    => $message->subject,
                'from_email' => $fromEmail,
                'from_name'  => $fromName,
                'to'         => $toList,
            ];

            if ($message->replyTo) {
                $msgPayload['headers'] = ['Reply-To' => $message->replyTo];
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => [
                    'key'     => $this->requireConfig('api_key'),
                    'message' => $msgPayload,
                ],
            ]);

            $body   = json_decode((string) $response->getBody(), true);
            $first  = $body[0] ?? [];
            $status = $first['status'] ?? '';

            if (in_array($status, ['sent', 'queued', 'scheduled'], true)) {
                return SendResult::success($this->key, $first['_id'] ?? null);
            }

            return SendResult::failure($this->key, $first['reject_reason'] ?? $status);
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
