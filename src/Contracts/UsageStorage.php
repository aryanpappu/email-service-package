<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Contracts;

interface UsageStorage
{
    public function getSentToday(string $providerKey): int;

    public function getSentThisHour(string $providerKey): int;

    public function getSentTotal(string $providerKey): int;

    public function incrementSent(string $providerKey): void;

    public function getConsecutiveFailures(string $providerKey): int;

    public function incrementFailures(string $providerKey): void;

    public function resetFailures(string $providerKey): void;

    public function getCoolingUntil(string $providerKey): ?int;

    public function setCooling(string $providerKey, int $minutes): void;

    public function clearCooling(string $providerKey): void;

    public function getLastUsedAt(string $providerKey): ?int;

    public function resetDaily(string $providerKey): void;

    public function resetHourly(string $providerKey): void;

    public function getAllStats(string $providerKey): array;
}
