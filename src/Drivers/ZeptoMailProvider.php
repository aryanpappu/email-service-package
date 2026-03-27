<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * ZeptoMail by Zoho — $2.50 per 10,000 emails, excellent deliverability.
 * Better alternative to Zoho SMTP. No spam account blocking.
 * API docs: https://www.zoho.com/zeptomail/help/api/email-sending.html
 */
class ZeptoMailProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.zeptomail.com/v1.1/email';

    public function getDriver(): string
    {
        return 'zeptomail';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');

            $toList = array_map(
                fn ($name, $email) => ['email_address' => ['address' => $email, 'name' => $name ?: $email]],
                $message->to,
                array_keys($message->to),
            );

            $payload = [
                'from'    => ['address' => $fromEmail, 'name' => $fromName],
                'to'      => $toList,
                'subject' => $message->subject,
            ];

            if ($message->htmlBody) {
                $payload['htmlbody'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $payload['textbody'] = $message->textBody;
            }

            if ($message->replyTo) {
                $payload['reply_to'] = [['address' => $message->replyTo, 'name' => $message->replyToName ?? '']];
            }

            $response = $client->post(self::API_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Zoho-enczapikey ' . $this->requireConfig('api_key'),
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $id   = $body['data'][0]['message_id'] ?? null;

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
