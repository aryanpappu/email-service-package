<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Elastic Email — 100 emails/day free tier.
 * API docs: https://elasticemail.com/developers/api-documentation
 */
class ElasticEmailProvider extends BaseProvider
{
    private const API_ENDPOINT = 'https://api.elasticemail.com/v2/email/send';

    public function getDriver(): string
    {
        return 'elasticemail';
    }

    public function send(MailMessage $message): SendResult
    {
        try {
            $client = new Client(['timeout' => 15]);

            $formParams = [
                'apikey'    => $this->requireConfig('api_key'),
                'from'      => $message->fromEmail ?: $this->requireConfig('from_email'),
                'fromName'  => $message->fromName ?: ($this->getFromName() ?? ''),
                'to'        => implode(';', array_keys($message->to)),
                'subject'   => $message->subject,
                'isTransactional' => true,
            ];

            if ($message->htmlBody) {
                $formParams['bodyHtml'] = $message->htmlBody;
            }

            if ($message->textBody) {
                $formParams['bodyText'] = $message->textBody;
            }

            if ($message->cc) {
                $formParams['cc'] = implode(';', array_keys($message->cc));
            }

            if ($message->bcc) {
                $formParams['bcc'] = implode(';', array_keys($message->bcc));
            }

            if ($message->replyTo) {
                $formParams['replyTo'] = $message->replyTo;
            }

            $response = $client->post(self::API_ENDPOINT, [
                'form_params' => $formParams,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (!($body['success'] ?? false)) {
                return SendResult::failure($this->key, $body['error'] ?? 'API returned success=false');
            }

            return SendResult::success($this->key, $body['data']['messageid'] ?? null);
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
