<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Storage;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use TechSolutionStuff\SmartMailer\Contracts\UsageStorage;

class DatabaseStorage implements UsageStorage
{
    public function __construct(private readonly Connection $db) {}

    public function getSentToday(string $providerKey): int
    {
        $record = $this->getDailyRecord($providerKey);
        return (int) ($record->sent_today ?? 0);
    }

    public function getSentThisHour(string $providerKey): int
    {
        $record = $this->getHourlyRecord($providerKey);
        return (int) ($record->sent_this_hour ?? 0);
    }

    public function getSentTotal(string $providerKey): int
    {
        $record = $this->db->table('smart_mailer_usage')
            ->where('provider_key', $providerKey)
            ->where('period_type', 'total')
            ->first();

        return (int) ($record->sent_total ?? 0);
    }

    public function incrementSent(string $providerKey): void
    {
        $today = Carbon::today()->toDateString();
        $hour  = Carbon::now()->format('H');

        // Daily — upsert with atomic increment
        $this->db->table('smart_mailer_usage')->upsert(
            [
                'provider_key' => $providerKey,
                'period_type'  => 'daily',
                'period_date'  => $today,
                'period_hour'  => null,
                'sent_today'   => 1,
                'sent_total'   => 1,
                'last_used_at' => Carbon::now(),
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ],
            ['provider_key', 'period_type', 'period_date'],
            ['sent_today' => $this->db->raw('sent_today + 1'), 'sent_total' => $this->db->raw('sent_total + 1'), 'last_used_at' => Carbon::now(), 'updated_at' => Carbon::now()]
        );

        // Hourly
        $this->db->table('smart_mailer_usage')->upsert(
            [
                'provider_key'  => $providerKey,
                'period_type'   => 'hourly',
                'period_date'   => $today,
                'period_hour'   => $hour,
                'sent_this_hour' => 1,
                'sent_total'    => 0,
                'last_used_at'  => Carbon::now(),
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ],
            ['provider_key', 'period_type', 'period_date', 'period_hour'],
            ['sent_this_hour' => $this->db->raw('sent_this_hour + 1'), 'last_used_at' => Carbon::now(), 'updated_at' => Carbon::now()]
        );

        // Global total
        $this->db->table('smart_mailer_usage')->upsert(
            [
                'provider_key' => $providerKey,
                'period_type'  => 'total',
                'period_date'  => null,
                'period_hour'  => null,
                'sent_total'   => 1,
                'last_used_at' => Carbon::now(),
                'created_at'   => Carbon::now(),
                'updated_at'   => Carbon::now(),
            ],
            ['provider_key', 'period_type'],
            ['sent_total' => $this->db->raw('sent_total + 1'), 'last_used_at' => Carbon::now(), 'updated_at' => Carbon::now()]
        );
    }

    public function getConsecutiveFailures(string $providerKey): int
    {
        $record = $this->db->table('smart_mailer_provider_state')
            ->where('provider_key', $providerKey)
            ->first();

        return (int) ($record->consecutive_failures ?? 0);
    }

    public function incrementFailures(string $providerKey): void
    {
        $this->db->table('smart_mailer_provider_state')->upsert(
            [
                'provider_key'          => $providerKey,
                'consecutive_failures'  => 1,
                'cooling_until'         => null,
                'created_at'            => Carbon::now(),
                'updated_at'            => Carbon::now(),
            ],
            ['provider_key'],
            ['consecutive_failures' => $this->db->raw('consecutive_failures + 1'), 'updated_at' => Carbon::now()]
        );
    }

    public function resetFailures(string $providerKey): void
    {
        $this->db->table('smart_mailer_provider_state')
            ->where('provider_key', $providerKey)
            ->update(['consecutive_failures' => 0, 'updated_at' => Carbon::now()]);
    }

    public function getCoolingUntil(string $providerKey): ?int
    {
        $record = $this->db->table('smart_mailer_provider_state')
            ->where('provider_key', $providerKey)
            ->first();

        if (!$record || !$record->cooling_until) {
            return null;
        }

        return (int) $record->cooling_until;
    }

    public function setCooling(string $providerKey, int $minutes): void
    {
        $until = Carbon::now()->addMinutes($minutes)->timestamp;

        $this->db->table('smart_mailer_provider_state')->upsert(
            [
                'provider_key'  => $providerKey,
                'cooling_until' => $until,
                'consecutive_failures' => 0,
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ],
            ['provider_key'],
            ['cooling_until' => $until, 'updated_at' => Carbon::now()]
        );
    }

    public function clearCooling(string $providerKey): void
    {
        $this->db->table('smart_mailer_provider_state')
            ->where('provider_key', $providerKey)
            ->update(['cooling_until' => null, 'updated_at' => Carbon::now()]);
    }

    public function getLastUsedAt(string $providerKey): ?int
    {
        // CRITICAL FIX: last_used_at is written to smart_mailer_usage (period_type=total), not provider_state
        $record = $this->db->table('smart_mailer_usage')
            ->where('provider_key', $providerKey)
            ->where('period_type', 'total')
            ->first();

        return ($record && $record->last_used_at) ? (int) Carbon::parse($record->last_used_at)->timestamp : null;
    }

    public function resetDaily(string $providerKey): void
    {
        $this->db->table('smart_mailer_usage')
            ->where('provider_key', $providerKey)
            ->where('period_type', 'daily')
            ->where('period_date', Carbon::today()->toDateString())
            ->update(['sent_today' => 0, 'updated_at' => Carbon::now()]);
    }

    public function resetHourly(string $providerKey): void
    {
        $this->db->table('smart_mailer_usage')
            ->where('provider_key', $providerKey)
            ->where('period_type', 'hourly')
            ->where('period_date', Carbon::today()->toDateString())
            ->where('period_hour', Carbon::now()->format('H'))
            ->update(['sent_this_hour' => 0, 'updated_at' => Carbon::now()]);
    }

    public function getAllStats(string $providerKey): array
    {
        return [
            'sent_today'           => $this->getSentToday($providerKey),
            'sent_this_hour'       => $this->getSentThisHour($providerKey),
            'sent_total'           => $this->getSentTotal($providerKey),
            'consecutive_failures' => $this->getConsecutiveFailures($providerKey),
            'cooling_until'        => $this->getCoolingUntil($providerKey),
            'last_used_at'         => $this->getLastUsedAt($providerKey),
        ];
    }

    private function getDailyRecord(string $providerKey): ?object
    {
        return $this->db->table('smart_mailer_usage')
            ->where('provider_key', $providerKey)
            ->where('period_type', 'daily')
            ->where('period_date', Carbon::today()->toDateString())
            ->first();
    }

    private function getHourlyRecord(string $providerKey): ?object
    {
        return $this->db->table('smart_mailer_usage')
            ->where('provider_key', $providerKey)
            ->where('period_type', 'hourly')
            ->where('period_date', Carbon::today()->toDateString())
            ->where('period_hour', Carbon::now()->format('H'))
            ->first();
    }
}
