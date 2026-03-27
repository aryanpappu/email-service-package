<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\Exceptions\SpamBlockedException;
use TechSolutionStuff\SmartMailer\Services\SpamGuard;

beforeEach(function (): void {
    $this->cache = new Repository(new ArrayStore());
    $this->config = [
        'defaults' => ['log_channel' => 'stack'],
        'spam_protection' => [
            'enabled'                            => true,
            'max_emails_per_recipient_per_hour'  => 3,
            'max_emails_per_recipient_per_day'   => 10,
            'max_sends_per_minute'               => 10,
            'blacklist_after_failures'           => 5,
            'blacklist_duration_minutes'         => 120,
            'block_disposable_emails'            => true,
            'blocked_domains'                    => ['blocked.com'],
        ],
    ];
    $this->guard = new SpamGuard($this->cache, $this->config);
});

function makeMessage(string $to = 'user@example.com'): MailMessage
{
    $msg = new MailMessage('from@test.com', 'Test', 'Subject');
    $msg->to($to);
    return $msg;
}

test('check passes for clean message', function (): void {
    expect(fn () => $this->guard->check(makeMessage()))->not->toThrow(SpamBlockedException::class);
});

test('check blocks disposable email domain', function (): void {
    expect(fn () => $this->guard->check(makeMessage('user@mailinator.com')))
        ->toThrow(SpamBlockedException::class, 'disposable');
});

test('check blocks explicitly blocked domain', function (): void {
    expect(fn () => $this->guard->check(makeMessage('user@blocked.com')))
        ->toThrow(SpamBlockedException::class, 'blocked');
});

test('check blocks manually blacklisted email', function (): void {
    $this->guard->addToBlacklist('spam@evil.com');

    expect(fn () => $this->guard->check(makeMessage('spam@evil.com')))
        ->toThrow(SpamBlockedException::class, 'blacklisted');
});

test('check blocks blacklisted domain', function (): void {
    $this->guard->addToBlacklist('evil.com');

    expect(fn () => $this->guard->check(makeMessage('anyone@evil.com')))
        ->toThrow(SpamBlockedException::class, 'blacklisted');
});

test('check blocks after hourly recipient limit exceeded', function (): void {
    $msg = makeMessage('flood@example.com');

    // Record 3 sends
    $this->guard->recordSent($msg);
    $this->guard->recordSent($msg);
    $this->guard->recordSent($msg);

    // 4th should be blocked
    expect(fn () => $this->guard->check($msg))
        ->toThrow(SpamBlockedException::class, 'hourly limit');
});

test('removeFromBlacklist allows sending again', function (): void {
    $this->guard->addToBlacklist('temp@example.com');
    $this->guard->removeFromBlacklist('temp@example.com');

    expect(fn () => $this->guard->check(makeMessage('temp@example.com')))
        ->not->toThrow(SpamBlockedException::class);
});

test('getBlacklist returns all entries', function (): void {
    $this->guard->addToBlacklist('a@example.com');
    $this->guard->addToBlacklist('b@example.com');

    expect($this->guard->getBlacklist())->toHaveCount(2);
});

test('check passes when spam protection is disabled', function (): void {
    $this->config['spam_protection']['enabled'] = false;
    $guard = new SpamGuard($this->cache, $this->config);

    $this->guard->addToBlacklist('user@mailinator.com');
    expect(fn () => $guard->check(makeMessage('user@mailinator.com')))
        ->not->toThrow(SpamBlockedException::class);
});
