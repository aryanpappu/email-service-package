<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * SparkPost / Bird — developer account with free monthly credits.
 * API docs: https://developers.sparkpost.com/api/transmissions/
 */
class SparkPostProvider extends BaseProvider
{
    private const API_ENDPOINT    = 'https://api.sparkpost.com/api/v1/transmissions';
    private const EU_API_ENDPOINT = 'https://api.eu.sparkpost.com/api/v1/transmissions';

    public function getDriver(): string
    {
        return 'sparkpost';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client   = new Client(['timeout' => 15]);
            $endpoint = ($this->getConfig('region') === 'eu') ? self::EU_API_ENDPOINT : self::API_ENDPOINT;

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');
            $from      = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;

            $recipients = array_map(
                fn ($name, $email) => ['address' => ['email' => $email, 'name' => $name ?: $email]],
                $message->to,
                array_keys($message->to),
            );

            $content = [
                'from'    => $from,
                'subject' => $message->subject,
            ];

            if ($message->htmlBody) {
                $content['html'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $content['text'] = $message->textBody;
            }

            if ($message->replyTo) {
                $content['reply_to'] = $message->replyTo;
            }

            $response = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => $this->requireConfig('api_key'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'recipients' => $recipients,
                    'content'    => $content,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $id   = $body['results']['id'] ?? null;

            return SendResult::success($this->key, $id);
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
