<?php

declare(strict_types=1);

use TechSolutionStuff\SmartMailer\Services\DomainResolver;

beforeEach(function (): void {
    $this->resolver = new DomainResolver([
        'domains' => [
            'default' => [
                'strategy'   => 'priority',
                'providers'  => ['p1', 'p2'],
                'from_email' => 'default@example.com',
            ],
            'client1.com' => [
                'strategy'   => 'least_used',
                'providers'  => ['p3'],
                'from_email' => 'hello@client1.com',
            ],
            '*.agency.com' => [
                'strategy'  => 'round_robin',
                'providers' => ['p4'],
            ],
        ],
    ]);
});

test('resolves exact domain match', function (): void {
    expect($this->resolver->resolve('client1.com'))->toBe('client1.com');
});

test('resolves wildcard domain match', function (): void {
    expect($this->resolver->resolve('sub.agency.com'))->toBe('*.agency.com');
});

test('falls back to default for unknown domain', function (): void {
    expect($this->resolver->resolve('unknown.com'))->toBe('default');
});

test('falls back to default for null domain', function (): void {
    expect($this->resolver->resolve(null))->toBe('default');
});

test('getProviderKeysForDomain returns correct providers', function (): void {
    expect($this->resolver->getProviderKeysForDomain('client1.com'))->toBe(['p3']);
});

test('getStrategyForDomain returns correct strategy', function (): void {
    expect($this->resolver->getStrategyForDomain('client1.com'))->toBe('least_used');
});

test('getFromForDomain returns from details', function (): void {
    $from = $this->resolver->getFromForDomain('client1.com');
    expect($from['email'])->toBe('hello@client1.com');
});
