<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Services;

use Illuminate\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use TechSolutionStuff\SmartMailer\Contracts\EmailProvider;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\ProviderStatus;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;
use TechSolutionStuff\SmartMailer\Events\AllProvidersExhausted;
use TechSolutionStuff\SmartMailer\Events\EmailFailed;
use TechSolutionStuff\SmartMailer\Events\EmailSent;
use TechSolutionStuff\SmartMailer\Events\ProviderCooling;
use TechSolutionStuff\SmartMailer\Events\ProviderExhausted;
use TechSolutionStuff\SmartMailer\Events\ProviderSwitched;
use TechSolutionStuff\SmartMailer\Exceptions\AllProvidersExhaustedException;
use TechSolutionStuff\SmartMailer\Strategies\LeastUsedStrategy;
use TechSolutionStuff\SmartMailer\Strategies\PriorityStrategy;
use TechSolutionStuff\SmartMailer\Strategies\RandomWeightedStrategy;
use TechSolutionStuff\SmartMailer\Strategies\RoundRobinStrategy;

class ProviderPool
{
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly UsageTracker $tracker,
        private readonly DomainResolver $domainResolver,
        private readonly SpamGuard $spamGuard,
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    /**
     * Send a message, automatically selecting and rotating providers.
     *
     * @throws AllProvidersExhaustedException
     * @throws \TechSolutionStuff\SmartMailer\Exceptions\SpamBlockedException
     */
    public function send(MailMessage $message, ?string $senderIp = null): SendResult
    {
        $domainKey  = $this->domainResolver->resolve($message->domain);
        $domainFrom = $this->domainResolver->getFromForDomain($domainKey);

        if (!$message->fromEmail && $domainFrom['email']) {
            $message->fromEmail = $domainFrom['email'];
        }
        if (!$message->fromName && $domainFrom['name']) {
            $message->fromName = $domainFrom['name'];
        }

        $this->spamGuard->check($message, $senderIp);

        $lastProvider = null;
        $attempted    = [];

        // MEDIUM FIX: Try all available providers, not just max_retries count
        // max_retries caps attempts to avoid infinite loops on large pools
        $maxAttempts = (int) ($this->config['defaults']['max_retries'] ?? 3);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $providerKey = $this->selectProvider($domainKey, $attempted);

            if ($providerKey === null) {
                break;
            }

            $attempted[] = $providerKey;

            if ($lastProvider !== null && $lastProvider !== $providerKey) {
                event(new ProviderSwitched($lastProvider, $providerKey, 'previous provider failed'));
            }

            // HIGH FIX: Clear expired cooling only when actually using the provider
            $this->tracker->clearExpiredCooling($providerKey);

            $provider     = $this->factory->make($providerKey);
            $lastProvider = $providerKey;

            $this->logDebug("Attempting send via [{$providerKey}]", $message);

            $result = $provider->send($message);

            if ($result->success) {
                $this->tracker->recordSuccess($providerKey);
                $this->spamGuard->recordSent($message);
                event(new EmailSent($providerKey, $message, $result->messageId));
                $this->logInfo("Email sent successfully via [{$providerKey}]", $message);
                return $result;
            }

            $this->tracker->recordFailure($providerKey);
            event(new EmailFailed($providerKey, $message, $result->error ?? 'Unknown error'));
            $this->logWarning("Provider [{$providerKey}] failed: {$result->error}", $message);

            if ($this->tracker->isCooling($providerKey)) {
                event(new ProviderCooling($providerKey));
            }
        }

        event(new AllProvidersExhausted($message->domain ?? 'default'));
        throw new AllProvidersExhaustedException($message->domain ?? 'default');
    }

    public function selectProvider(string $domainKey, array $exclude = []): ?string
    {
        $providerKeys = $this->domainResolver->getProviderKeysForDomain($domainKey);

        // HIGH FIX: Evaluate cooling outside of array_filter to avoid clearCooling side-effects in filter predicate
        $available = [];
        foreach ($providerKeys as $key) {
            if (in_array($key, $exclude, true)) {
                continue;
            }

            $providerConfig = $this->config['providers'][$key] ?? [];
            if (!($providerConfig['enabled'] ?? true)) {
                continue;
            }

            // Pure read — does not mutate cooling state
            if ($this->tracker->isCooling($key)) {
                continue;
            }

            if (!$this->tracker->canSend($key)) {
                event(new ProviderExhausted($key, 'daily_or_hourly'));
                continue;
            }

            $available[] = $key;
        }

        if (empty($available)) {
            return null;
        }

        $strategyName = $this->domainResolver->getStrategyForDomain($domainKey);
        $strategy     = $this->resolveStrategy($strategyName);

        return $strategy->select($available, $this->tracker);
    }

    /** @return ProviderStatus[] */
    public function getAllStatuses(): array
    {
        $statuses = [];
        foreach (array_keys($this->config['providers'] ?? []) as $key) {
            $statuses[$key] = $this->tracker->getStatus($key);
        }
        return $statuses;
    }

    public function resetProvider(string $providerKey): void
    {
        $this->tracker->resetProvider($providerKey);
    }

    private function resolveStrategy(string $name): \TechSolutionStuff\SmartMailer\Contracts\RotationStrategy
    {
        return match ($name) {
            'round_robin'     => new RoundRobinStrategy($this->cache),
            'least_used'      => new LeastUsedStrategy(),
            'random_weighted' => new RandomWeightedStrategy(),
            default           => new PriorityStrategy($this->config['providers'] ?? []),
        };
    }

    private function logDebug(string $msg, MailMessage $message): void
    {
        Log::channel($this->config['defaults']['log_channel'] ?? 'stack')
            ->debug("SmartMailer: {$msg}", ['to' => $message->getPrimaryRecipient()]);
    }

    private function logInfo(string $msg, MailMessage $message): void
    {
        Log::channel($this->config['defaults']['log_channel'] ?? 'stack')
            ->info("SmartMailer: {$msg}", ['to' => $message->getPrimaryRecipient()]);
    }

    private function logWarning(string $msg, MailMessage $message): void
    {
        Log::channel($this->config['defaults']['log_channel'] ?? 'stack')
            ->warning("SmartMailer: {$msg}", ['to' => $message->getPrimaryRecipient()]);
    }
}
