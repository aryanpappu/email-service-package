<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use TechSolutionStuff\SmartMailer\Contracts\EmailProvider;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;
use TechSolutionStuff\SmartMailer\Exceptions\AllProvidersExhaustedException;
use TechSolutionStuff\SmartMailer\Services\DomainResolver;
use TechSolutionStuff\SmartMailer\Services\ProviderFactory;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;
use TechSolutionStuff\SmartMailer\Services\SpamGuard;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;
use TechSolutionStuff\SmartMailer\Storage\CacheStorage;

function makePool(array $providerResults = []): array
{
    $cache   = new Repository(new ArrayStore());
    $storage = new CacheStorage($cache);
    $config  = [
        'defaults' => [
            'strategy'        => 'priority',
            'cooling_minutes' => 60,
            'max_retries'     => 3,
            'log_channel'     => 'stack',
        ],
        'spam_protection' => [
            'enabled'                           => true,
            'max_emails_per_recipient_per_hour' => 100,
            'max_emails_per_recipient_per_day'  => 1000,
            'max_sends_per_minute'              => 1000,
            'blacklist_after_failures'          => 5,
            'block_disposable_emails'           => false,
            'blocked_domains'                   => [],
        ],
        'providers' => [
            'p1' => ['driver' => 'mock', 'enabled' => true, 'priority' => 1, 'daily_limit' => 10, 'hourly_limit' => 5],
            'p2' => ['driver' => 'mock', 'enabled' => true, 'priority' => 2, 'daily_limit' => 10, 'hourly_limit' => 5],
        ],
        'domains' => [
            'default' => [
                'strategy'  => 'priority',
                'providers' => ['p1', 'p2'],
            ],
        ],
    ];

    $tracker  = new UsageTracker($storage, $config);
    $resolver = new DomainResolver($config);
    $guard    = new SpamGuard($cache, $config);

    // Build mock factory
    $factory = Mockery::mock(ProviderFactory::class);

    foreach ($providerResults as $key => $results) {
        $provider = Mockery::mock(EmailProvider::class);
        $queue    = is_array($results) ? $results : [$results];
        $calls    = $provider->shouldReceive('send');
        foreach ($queue as $result) {
            $calls->andReturn($result)->once();
        }
        $factory->shouldReceive('make')->with($key)->andReturn($provider);
    }

    $pool = new ProviderPool($factory, $tracker, $resolver, $guard, $cache, $config);

    return [$pool, $tracker];
}

function testMessage(string $to = 'user@example.com'): MailMessage
{
    $msg = new MailMessage('from@test.com', 'Test', 'Test Subject');
    $msg->to($to);
    return $msg;
}

test('sends successfully via first available provider', function (): void {
    [$pool] = makePool([
        'p1' => SendResult::success('p1', 'msg-123'),
    ]);

    $result = $pool->send(testMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerKey)->toBe('p1')
        ->and($result->messageId)->toBe('msg-123');
});

test('falls back to second provider when first fails', function (): void {
    [$pool] = makePool([
        'p1' => SendResult::failure('p1', 'SMTP connection refused'),
        'p2' => SendResult::success('p2', 'msg-456'),
    ]);

    $result = $pool->send(testMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerKey)->toBe('p2');
});

test('throws AllProvidersExhaustedException when all fail', function (): void {
    [$pool] = makePool([
        'p1' => [
            SendResult::failure('p1', 'fail'),
            SendResult::failure('p1', 'fail'),
            SendResult::failure('p1', 'fail'),
        ],
        'p2' => [
            SendResult::failure('p2', 'fail'),
            SendResult::failure('p2', 'fail'),
            SendResult::failure('p2', 'fail'),
        ],
    ]);

    expect(fn () => $pool->send(testMessage()))
        ->toThrow(AllProvidersExhaustedException::class);
});

test('skips provider that has reached daily limit', function (): void {
    [$pool, $tracker] = makePool([
        'p2' => SendResult::success('p2', 'msg-via-p2'),
    ]);

    // Exhaust p1's daily limit
    for ($i = 0; $i < 10; $i++) {
        $tracker->recordSuccess('p1');
    }

    $result = $pool->send(testMessage());

    expect($result->success)->toBeTrue()
        ->and($result->providerKey)->toBe('p2');
});

test('skips provider that is cooling', function (): void {
    [$pool, $tracker] = makePool([
        'p2' => SendResult::success('p2', 'msg-via-p2'),
    ]);

    $tracker->setCooling('p1', 60);

    $result = $pool->send(testMessage());

    expect($result->providerKey)->toBe('p2');
});

test('getAllStatuses returns status for all providers', function (): void {
    // Need real factory for this
    $cache   = new Repository(new ArrayStore());
    $storage = new CacheStorage($cache);
    $config  = [
        'defaults' => ['strategy' => 'priority', 'cooling_minutes' => 60, 'max_retries' => 3, 'log_channel' => 'stack'],
        'spam_protection' => ['enabled' => false, 'blocked_domains' => [], 'block_disposable_emails' => false, 'max_emails_per_recipient_per_hour' => 999, 'max_emails_per_recipient_per_day' => 999, 'max_sends_per_minute' => 999, 'blacklist_after_failures' => 5],
        'providers' => [
            'pa' => ['driver' => 'smtp', 'enabled' => true, 'priority' => 1, 'host' => 'h', 'username' => 'u', 'password' => 'p', 'daily_limit' => 100, 'hourly_limit' => 50],
            'pb' => ['driver' => 'smtp', 'enabled' => true, 'priority' => 2, 'host' => 'h2', 'username' => 'u2', 'password' => 'p2', 'daily_limit' => 50, 'hourly_limit' => 20],
        ],
        'domains' => ['default' => ['strategy' => 'priority', 'providers' => ['pa', 'pb']]],
    ];

    $tracker  = new UsageTracker($storage, $config);
    $factory  = new ProviderFactory($config);
    $resolver = new DomainResolver($config);
    $guard    = new SpamGuard($cache, $config);
    $pool     = new ProviderPool($factory, $tracker, $resolver, $guard, $cache, $config);

    $statuses = $pool->getAllStatuses();

    expect($statuses)->toHaveKeys(['pa', 'pb'])
        ->and($statuses['pa']->dailyLimit)->toBe(100)
        ->and($statuses['pb']->dailyLimit)->toBe(50);
});
