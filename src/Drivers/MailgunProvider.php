<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Mailgun — 100 emails/day free (Flex plan) or trial credits.
 * API docs: https://documentation.mailgun.com/en/latest/api-sending.html
 */
class MailgunProvider extends BaseProvider
{
    public function getDriver(): string
    {
        return 'mailgun';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $domain = $this->requireConfig('domain');
            $region = $this->getConfig('region', 'us');
            $base   = $region === 'eu' ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';

            $client = new Client(['timeout' => 15]);

            $formParams = [
                'from'    => $this->buildAddress($message->fromEmail ?: $this->requireConfig('from_email'), $message->fromName ?: ($this->getFromName() ?? '')),
                'to'      => implode(',', $this->buildAddressList($message->to)),
                'subject' => $message->subject,
            ];

            if ($message->htmlBody) {
                $formParams['html'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $formParams['text'] = $message->textBody;
            }

            if ($message->cc) {
                $formParams['cc'] = implode(',', $this->buildAddressList($message->cc));
            }

            if ($message->bcc) {
                $formParams['bcc'] = implode(',', $this->buildAddressList($message->bcc));
            }

            if ($message->replyTo) {
                $formParams['h:Reply-To'] = $this->buildAddress($message->replyTo, $message->replyToName ?? '');
            }

            foreach ($message->headers as $name => $value) {
                $formParams["h:{$name}"] = $value;
            }

            $response = $client->post("{$base}/v3/{$domain}/messages", [
                'auth'        => ['api', $this->requireConfig('api_key')],
                'form_params' => $formParams,
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
        $this->requireConfig('domain');
    }

    private function buildAddress(string $email, string $name = ''): string
    {
        return $name ? "{$name} <{$email}>" : $email;
    }

    private function buildAddressList(array $recipients): array
    {
        return array_map(
            fn ($name, $email) => $this->buildAddress($email, $name),
            $recipients,
            array_keys($recipients),
        );
    }
}
