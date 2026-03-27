<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Services;

class DomainResolver
{
    public function __construct(private readonly array $config) {}

    /**
     * Resolve the best matching domain config key for a given domain string.
     * Falls back to 'default' if no match found.
     */
    public function resolve(?string $domain): string
    {
        if (!$domain) {
            return 'default';
        }

        $domains = $this->config['domains'] ?? [];

        // Exact match
        if (isset($domains[$domain])) {
            return $domain;
        }

        // Wildcard match: *.example.com
        foreach (array_keys($domains) as $configuredDomain) {
            if (str_starts_with($configuredDomain, '*.')) {
                $pattern = substr($configuredDomain, 2); // Remove *. prefix
                if (str_ends_with($domain, '.' . $pattern) || $domain === $pattern) {
                    return $configuredDomain;
                }
            }
        }

        return 'default';
    }

    public function getDomainConfig(string $domainKey): array
    {
        return $this->config['domains'][$domainKey] ?? $this->config['domains']['default'] ?? [];
    }

    public function getProviderKeysForDomain(string $domainKey): array
    {
        $domainConfig = $this->getDomainConfig($domainKey);
        return $domainConfig['providers'] ?? [];
    }

    public function getStrategyForDomain(string $domainKey): string
    {
        $domainConfig = $this->getDomainConfig($domainKey);
        return $domainConfig['strategy'] ?? $this->config['defaults']['strategy'] ?? 'priority';
    }

    public function getFromForDomain(string $domainKey): array
    {
        $domainConfig = $this->getDomainConfig($domainKey);
        return [
            'email' => $domainConfig['from_email'] ?? null,
            'name'  => $domainConfig['from_name'] ?? null,
        ];
    }
}
