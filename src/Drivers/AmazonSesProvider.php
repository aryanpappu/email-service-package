<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

/**
 * Amazon SES — 62,000 emails/month free (when sent from EC2), else $0.10/1,000.
 * Uses AWS Signature V4 for authentication (no SDK required).
 * API docs: https://docs.aws.amazon.com/ses/latest/APIReference/API_SendEmail.html
 */
class AmazonSesProvider extends BaseProvider
{
    /** Allowed AWS region format: us-east-1, eu-west-1, ap-southeast-2, etc. */
    private const REGION_PATTERN = '/^[a-z]{2}-[a-z]+-\d+$/';

    public function getDriver(): string
    {
        return 'ses';
    }

    public function send(MailMessage $message): SendResult
    {
        if (empty($message->to)) {
            return SendResult::failure($this->key, 'No recipients specified.');
        }

        try {
            $region    = $this->getConfig('region', 'us-east-1');
            $accessKey = $this->requireConfig('key');
            $secretKey = $this->requireConfig('secret');

            // SEC-2: Validate region to prevent SSRF via URL injection
            if (!preg_match(self::REGION_PATTERN, $region)) {
                return SendResult::failure($this->key, "Invalid AWS region format: [{$region}]");
            }

            $endpoint = "https://email.{$region}.amazonaws.com";

            $fromEmail = $message->fromEmail ?: $this->requireConfig('from_email');
            $fromName  = $message->fromName ?: ($this->getFromName() ?? '');
            $from      = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;

            // Build form-encoded params for POST body
            $params = [
                'Action'                      => 'SendEmail',
                'Source'                      => $from,
                'Message.Subject.Data'        => $message->subject,
                'Message.Subject.Charset'     => 'UTF-8',
            ];

            // CRITICAL FIX: Build To address list without duplicate/overwrite
            $i = 1;
            foreach (array_keys($message->to) as $email) {
                $params["Destination.ToAddresses.member.{$i}"] = $email;
                $i++;
            }

            $j = 1;
            foreach (array_keys($message->cc) as $email) {
                $params["Destination.CcAddresses.member.{$j}"] = $email;
                $j++;
            }

            $k = 1;
            foreach (array_keys($message->bcc) as $email) {
                $params["Destination.BccAddresses.member.{$k}"] = $email;
                $k++;
            }

            if ($message->htmlBody) {
                $params['Message.Body.Html.Data']    = $message->htmlBody;
                $params['Message.Body.Html.Charset'] = 'UTF-8';
            }

            if ($message->textBody) {
                $params['Message.Body.Text.Data']    = $message->textBody;
                $params['Message.Body.Text.Charset'] = 'UTF-8';
            }

            if ($message->replyTo) {
                $params['ReplyToAddresses.member.1'] = $message->replyTo;
            }

            // Build URL-encoded body
            ksort($params);
            $bodyStr = http_build_query($params);

            $date      = gmdate('Ymd');
            $datetime  = gmdate('Ymd\THis\Z');
            $service   = 'ses';
            $host      = "email.{$region}.amazonaws.com";

            // CRITICAL FIX: Correct AWS Sig V4 — empty query string, body hash from actual body
            $payloadHash = hash('sha256', $bodyStr);

            $canonicalRequest = implode("\n", [
                'POST',
                '/',
                '',  // empty query string
                "host:{$host}",
                "x-amz-date:{$datetime}",
                '',  // blank line after headers
                'host;x-amz-date',
                $payloadHash,
            ]);

            $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
            $stringToSign    = implode("\n", [
                'AWS4-HMAC-SHA256',
                $datetime,
                $credentialScope,
                hash('sha256', $canonicalRequest),
            ]);

            $signingKey = hash_hmac('sha256', 'aws4_request',
                hash_hmac('sha256', $service,
                    hash_hmac('sha256', $region,
                        hash_hmac('sha256', $date, 'AWS4' . $secretKey, true),
                        true),
                    true),
                true);

            $signature     = hash_hmac('sha256', $stringToSign, $signingKey);
            $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders=host;x-amz-date, Signature={$signature}";

            $client   = new Client(['timeout' => 15]);
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                    'X-Amz-Date'    => $datetime,
                    'Authorization' => $authorization,
                ],
                'body' => $bodyStr,
            ]);

            $xml       = simplexml_load_string((string) $response->getBody());
            $messageId = (string) ($xml->SendEmailResult->MessageId ?? '');

            return SendResult::success($this->key, $messageId ?: null);
        } catch (GuzzleException $e) {
            // SEC-1: Sanitize error — do not expose Authorization header or credentials
            $errorBody = method_exists($e, 'getResponse') && $e->getResponse()
                ? (string) $e->getResponse()->getBody()
                : 'HTTP request failed';

            return SendResult::failure($this->key, $this->sanitizeError($errorBody));
        } catch (\Throwable $e) {
            return SendResult::failure($this->key, $this->sanitizeError($e->getMessage()));
        }
    }

    protected function validateConfig(): void
    {
        $this->requireConfig('key');
        $this->requireConfig('secret');
    }

    /**
     * Strip Authorization headers and credentials from error messages before logging.
     */
    private function sanitizeError(string $message): string
    {
        // Remove Authorization header values if they somehow appear in the message
        $message = preg_replace('/Authorization:\s*AWS4-HMAC-SHA256[^\r\n]*/i', 'Authorization: [redacted]', $message);
        $message = preg_replace('/Credential=[A-Z0-9]+\//i', 'Credential=[redacted]/', $message);
        return $message;
    }
}
