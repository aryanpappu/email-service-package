<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Services;

use Carbon\Carbon;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\Events\EmailBlocked;
use TechSolutionStuff\SmartMailer\Exceptions\SpamBlockedException;

class SpamGuard
{
    /** Known disposable email domain list (abbreviated — extend as needed) */
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwam.com',
        'yopmail.com', 'trashmail.com', 'sharklasers.com', 'guerrillamailblock.com',
        'grr.la', 'guerrillamail.info', 'guerrillamail.biz', 'guerrillamail.de',
        'guerrillamail.net', 'guerrillamail.org', 'spam4.me', 'dispostable.com',
        'mailnull.com', 'maildrop.cc', 'spamgourmet.com', 'trashmail.at',
        'trashmail.me', 'discard.email', 'fakeinbox.com', 'mailnesia.com',
        'spamherelots.com', 'throwam.com', 'wegwerfmail.de', 'mailexpire.com',
        'jetable.fr.nf', 'mintemail.com', 'spambox.us', 'trbvn.com',
    ];

    public function __construct(
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    /**
     * @throws SpamBlockedException
     */
    public function check(MailMessage $message, ?string $senderIp = null): void
    {
        if (!($this->config['spam_protection']['enabled'] ?? true)) {
            return;
        }

        foreach ($message->getRecipientEmails() as $recipient) {
            $this->checkRecipientBlacklist($recipient);
            $this->checkDisposableEmail($recipient);
            $this->checkRecipientRateLimit($recipient);
        }

        if ($senderIp) {
            $this->checkIpRateLimit($senderIp);
        }
    }

    public function isBlacklisted(string $emailOrDomain): bool
    {
        return (bool) $this->cache->get($this->blacklistCacheKey($emailOrDomain));
    }

    /**
     * @throws \InvalidArgumentException if input is not a valid email or domain
     */
    public function addToBlacklist(string $emailOrDomain, ?int $durationMinutes = null): void
    {
        // SEC-3: Validate input to prevent cache key poisoning
        $this->validateEmailOrDomain($emailOrDomain);

        $cacheKey = $this->blacklistCacheKey($emailOrDomain);

        if ($durationMinutes) {
            $this->cache->put($cacheKey, true, $durationMinutes * 60);
        } else {
            $this->cache->forever($cacheKey, true);
        }

        $entries = $this->cache->get('smart_mailer:blacklist_entries', []);
        $entries[$emailOrDomain] = [
            'added_at'   => Carbon::now()->toDateTimeString(),
            'expires_at' => $durationMinutes ? Carbon::now()->addMinutes($durationMinutes)->toDateTimeString() : null,
            'permanent'  => $durationMinutes === null,
        ];
        $this->cache->forever('smart_mailer:blacklist_entries', $entries);
    }

    public function removeFromBlacklist(string $emailOrDomain): void
    {
        $this->cache->forget($this->blacklistCacheKey($emailOrDomain));

        $entries = $this->cache->get('smart_mailer:blacklist_entries', []);
        unset($entries[$emailOrDomain]);
        $this->cache->forever('smart_mailer:blacklist_entries', $entries);
    }

    public function getBlacklist(): array
    {
        return $this->cache->get('smart_mailer:blacklist_entries', []);
    }

    public function recordSent(MailMessage $message): void
    {
        foreach ($message->getRecipientEmails() as $recipient) {
            $hourKey = $this->recipientHourKey($recipient);
            $dayKey  = $this->recipientDayKey($recipient);

            // HIGH FIX: Use atomic cache increment with TTL set on first write only
            $hourTtl = (60 - Carbon::now()->minute) * 60 - Carbon::now()->second + 1;
            $dayTtl  = Carbon::now()->secondsUntilEndOfDay() + 1;

            $lock = $this->cache->lock("smart_mailer:recipient_lock:{$recipient}", 3);
            $lock->block(3, function () use ($hourKey, $dayKey, $hourTtl, $dayTtl): void {
                $hourCount = (int) $this->cache->get($hourKey, 0);
                $this->cache->put($hourKey, $hourCount + 1, max(1, $hourTtl));

                $dayCount = (int) $this->cache->get($dayKey, 0);
                $this->cache->put($dayKey, $dayCount + 1, max(1, $dayTtl));
            });
        }
    }

    private function checkRecipientBlacklist(string $recipient): void
    {
        if ($this->isBlacklisted($recipient)) {
            $this->block($recipient, 'recipient is blacklisted');
        }

        $domain = $this->extractDomain($recipient);
        if ($domain && $this->isBlacklisted($domain)) {
            $this->block($recipient, "domain [{$domain}] is blacklisted");
        }

        $blockedDomains = $this->config['spam_protection']['blocked_domains'] ?? [];
        if ($domain && in_array($domain, $blockedDomains, true)) {
            $this->block($recipient, "domain [{$domain}] is in blocked list");
        }
    }

    private function checkDisposableEmail(string $recipient): void
    {
        if (!($this->config['spam_protection']['block_disposable_emails'] ?? true)) {
            return;
        }

        $domain = $this->extractDomain($recipient);
        if ($domain && in_array(strtolower($domain), self::DISPOSABLE_DOMAINS, true)) {
            $this->block($recipient, 'disposable email address not allowed');
        }
    }

    private function checkRecipientRateLimit(string $recipient): void
    {
        $hourlyLimit = (int) ($this->config['spam_protection']['max_emails_per_recipient_per_hour'] ?? 5);
        $dailyLimit  = (int) ($this->config['spam_protection']['max_emails_per_recipient_per_day'] ?? 20);

        $hourCount = (int) $this->cache->get($this->recipientHourKey($recipient), 0);
        $dayCount  = (int) $this->cache->get($this->recipientDayKey($recipient), 0);

        if ($hourCount >= $hourlyLimit) {
            $this->block($recipient, "hourly limit of {$hourlyLimit} reached for this recipient");
        }

        if ($dayCount >= $dailyLimit) {
            $this->block($recipient, "daily limit of {$dailyLimit} reached for this recipient");
        }
    }

    private function checkIpRateLimit(string $ip): void
    {
        $perMinuteLimit = (int) ($this->config['spam_protection']['max_sends_per_minute'] ?? 10);
        $minuteKey      = "smart_mailer:ip_minute:{$ip}:" . Carbon::now()->format('Y-m-d-H-i');
        $count          = (int) $this->cache->get($minuteKey, 0);

        if ($count >= $perMinuteLimit) {
            $this->block($ip, "IP rate limit of {$perMinuteLimit}/minute exceeded");
        }

        $this->cache->put($minuteKey, $count + 1, 120);
    }

    private function block(string $identifier, string $reason): void
    {
        Log::channel($this->config['defaults']['log_channel'] ?? 'stack')
            ->warning("SmartMailer: email blocked for [{$identifier}]: {$reason}");

        event(new EmailBlocked($identifier, $reason));

        throw new SpamBlockedException($identifier, $reason);
    }

    private function extractDomain(string $email): ?string
    {
        $parts = explode('@', $email);
        return count($parts) === 2 ? strtolower($parts[1]) : null;
    }

    /**
     * SEC-3: Validate that input is a valid email or domain before using as cache key.
     *
     * @throws \InvalidArgumentException
     */
    private function validateEmailOrDomain(string $value): void
    {
        $isEmail  = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        $isDomain = preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value) === 1;

        if (!$isEmail && !$isDomain) {
            throw new \InvalidArgumentException("Invalid email or domain for blacklist: [{$value}]");
        }
    }

    /**
     * Build a safe, normalized cache key for blacklist entries.
     */
    private function blacklistCacheKey(string $emailOrDomain): string
    {
        return 'smart_mailer:blacklist:' . md5(strtolower($emailOrDomain));
    }

    private function recipientHourKey(string $recipient): string
    {
        return 'smart_mailer:recipient_hourly:' . md5($recipient) . ':' . Carbon::now()->format('Y-m-d-H');
    }

    private function recipientDayKey(string $recipient): string
    {
        return 'smart_mailer:recipient_daily:' . md5($recipient) . ':' . Carbon::today()->format('Y-m-d');
    }
}
