<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;
use TechSolutionStuff\SmartMailer\Storage\CacheStorage;
use TechSolutionStuff\SmartMailer\Strategies\LeastUsedStrategy;
use TechSolutionStuff\SmartMailer\Strategies\PriorityStrategy;
use TechSolutionStuff\SmartMailer\Strategies\RandomWeightedStrategy;
use TechSolutionStuff\SmartMailer\Strategies\RoundRobinStrategy;

beforeEach(function (): void {
    $cache         = new Repository(new ArrayStore());
    $this->cache   = $cache;
    $storage       = new CacheStorage($cache);
    $this->config  = [
        'defaults'  => ['cooling_minutes' => 60],
        'spam_protection' => ['blacklist_after_failures' => 5],
        'providers' => [
            'p1' => ['enabled' => true, 'daily_limit' => 100, 'hourly_limit' => 50, 'priority' => 1],
            'p2' => ['enabled' => true, 'daily_limit' => 50,  'hourly_limit' => 25, 'priority' => 2],
            'p3' => ['enabled' => true, 'daily_limit' => 200, 'hourly_limit' => 80, 'priority' => 3],
        ],
    ];
    $this->tracker = new UsageTracker($storage, $this->config);
});

test('PriorityStrategy selects lowest priority number first', function (): void {
    $strategy = new PriorityStrategy($this->config['providers']);
    $selected  = $strategy->select(['p3', 'p1', 'p2'], $this->tracker);
    expect($selected)->toBe('p1');
});

test('PriorityStrategy returns null for empty list', function (): void {
    $strategy = new PriorityStrategy($this->config['providers']);
    expect($strategy->select([], $this->tracker))->toBeNull();
});

test('RoundRobinStrategy cycles providers', function (): void {
    $strategy  = new RoundRobinStrategy($this->cache);
    $providers = ['p1', 'p2', 'p3'];

    $first  = $strategy->select($providers, $this->tracker);
    $second = $strategy->select($providers, $this->tracker);
    $third  = $strategy->select($providers, $this->tracker);
    $fourth = $strategy->select($providers, $this->tracker);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBe($first)
        ->and($third)->not->toBe($second)
        ->and($fourth)->toBe($first); // Wraps around
});

test('LeastUsedStrategy picks provider with most remaining quota', function (): void {
    // p1 has 100 limit, p2 has 50, p3 has 200 — p3 has most remaining
    $strategy = new LeastUsedStrategy();
    $selected  = $strategy->select(['p1', 'p2', 'p3'], $this->tracker);
    expect($selected)->toBe('p3');
});

test('LeastUsedStrategy accounts for sent emails', function (): void {
    // Send 90 on p3, leaving it with only 110 remaining
    // p1 still has 100 remaining — but p3 has 110, so p3 still wins
    for ($i = 0; $i < 90; $i++) {
        $this->tracker->recordSuccess('p3');
    }

    $strategy = new LeastUsedStrategy();
    $selected  = $strategy->select(['p1', 'p3'], $this->tracker);
    expect($selected)->toBe('p3'); // 110 remaining vs 100
});

test('RandomWeightedStrategy returns one of the providers', function (): void {
    $strategy = new RandomWeightedStrategy();
    $selected  = $strategy->select(['p1', 'p2', 'p3'], $this->tracker);
    expect($selected)->toBeIn(['p1', 'p2', 'p3']);
});

test('RandomWeightedStrategy returns null for empty list', function (): void {
    $strategy = new RandomWeightedStrategy();
    expect($strategy->select([], $this->tracker))->toBeNull();
});
