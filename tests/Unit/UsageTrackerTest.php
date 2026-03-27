<?php

declare(strict_types=1);

use TechSolutionStuff\SmartMailer\Storage\CacheStorage;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

beforeEach(function (): void {
    $cache         = new Repository(new ArrayStore());
    $storage       = new CacheStorage($cache);
    $this->config  = [
        'defaults'  => ['cooling_minutes' => 60],
        'spam_protection' => ['blacklist_after_failures' => 5],
        'providers' => [
            'test_p' => [
                'enabled'      => true,
                'daily_limit'  => 10,
                'hourly_limit' => 5,
            ],
        ],
    ];
    $this->tracker = new UsageTracker($storage, $this->config);
    $this->storage = $storage;
});

test('canSend returns true when under limits', function (): void {
    expect($this->tracker->canSend('test_p'))->toBeTrue();
});

test('canSend returns false when daily limit reached', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->tracker->recordSuccess('test_p');
    }

    expect($this->tracker->canSend('test_p'))->toBeFalse();
});

test('canSend returns false when hourly limit reached', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->tracker->recordSuccess('test_p');
    }

    expect($this->tracker->canSend('test_p'))->toBeFalse();
});

test('recordSuccess increments counters', function (): void {
    $this->tracker->recordSuccess('test_p');
    $this->tracker->recordSuccess('test_p');

    $status = $this->tracker->getStatus('test_p');
    expect($status->sentToday)->toBe(2)
        ->and($status->sentThisHour)->toBe(2)
        ->and($status->sentTotal)->toBe(2);
});

test('recordSuccess resets consecutive failures', function (): void {
    $this->storage->incrementFailures('test_p');
    $this->storage->incrementFailures('test_p');

    $this->tracker->recordSuccess('test_p');

    expect($this->storage->getConsecutiveFailures('test_p'))->toBe(0);
});

test('recordFailure increments failure count', function (): void {
    $this->tracker->recordFailure('test_p');
    $this->tracker->recordFailure('test_p');

    expect($this->storage->getConsecutiveFailures('test_p'))->toBe(2);
});

test('recordFailure triggers cooling after threshold', function (): void {
    // Threshold is 5 failures
    for ($i = 0; $i < 5; $i++) {
        $this->tracker->recordFailure('test_p');
    }

    expect($this->tracker->isCooling('test_p'))->toBeTrue();
});

test('isCooling returns false when not cooling', function (): void {
    expect($this->tracker->isCooling('test_p'))->toBeFalse();
});

test('setCooling and isCooling work correctly', function (): void {
    $this->tracker->setCooling('test_p', 60);

    expect($this->tracker->isCooling('test_p'))->toBeTrue();
    expect($this->tracker->canSend('test_p'))->toBeFalse();
});

test('resetProvider clears all counters', function (): void {
    $this->tracker->recordSuccess('test_p');
    $this->tracker->recordSuccess('test_p');
    $this->tracker->setCooling('test_p', 60);

    $this->tracker->resetProvider('test_p');

    $status = $this->tracker->getStatus('test_p');
    expect($status->sentToday)->toBe(0)
        ->and($status->isCooling)->toBeFalse();
});

test('getRemainingToday calculates correctly', function (): void {
    $this->tracker->recordSuccess('test_p');
    $this->tracker->recordSuccess('test_p');
    $this->tracker->recordSuccess('test_p');

    expect($this->tracker->getRemainingToday('test_p'))->toBe(7);
});

test('canSend returns false for disabled provider', function (): void {
    $this->config['providers']['disabled_p'] = ['enabled' => false, 'daily_limit' => 100, 'hourly_limit' => 50];
    $tracker = new UsageTracker($this->storage, $this->config);

    expect($tracker->canSend('disabled_p'))->toBeFalse();
});
