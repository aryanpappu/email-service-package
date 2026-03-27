<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use TechSolutionStuff\SmartMailer\SmartMailerServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SmartMailerServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'SmartMailer' => \TechSolutionStuff\SmartMailer\Facades\SmartMailer::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Use array cache for tests — no external dependencies
        $app['config']->set('cache.default', 'array');

        // Minimal provider config for tests
        $app['config']->set('smart-mailer', [
            'storage'  => 'cache',
            'defaults' => [
                'strategy'        => 'priority',
                'cooling_minutes' => 60,
                'max_retries'     => 3,
                'log_channel'     => 'stack',
            ],
            'providers' => [
                'test_provider_1' => [
                    'driver'       => 'smtp',
                    'enabled'      => true,
                    'priority'     => 1,
                    'host'         => 'smtp.test.com',
                    'username'     => 'test@test.com',
                    'password'     => 'secret',
                    'daily_limit'  => 100,
                    'hourly_limit' => 20,
                    'from_email'   => 'test@test.com',
                    'from_name'    => 'Test',
                ],
                'test_provider_2' => [
                    'driver'       => 'smtp',
                    'enabled'      => true,
                    'priority'     => 2,
                    'host'         => 'smtp2.test.com',
                    'username'     => 'test2@test.com',
                    'password'     => 'secret2',
                    'daily_limit'  => 50,
                    'hourly_limit' => 10,
                    'from_email'   => 'test2@test.com',
                    'from_name'    => 'Test 2',
                ],
            ],
            'domains' => [
                'default' => [
                    'strategy'   => 'priority',
                    'providers'  => ['test_provider_1', 'test_provider_2'],
                    'from_email' => 'noreply@example.com',
                    'from_name'  => 'Test App',
                ],
            ],
            'spam_protection' => [
                'enabled'                            => true,
                'max_emails_per_recipient_per_hour'  => 5,
                'max_emails_per_recipient_per_day'   => 20,
                'max_sends_per_minute'               => 10,
                'blacklist_after_failures'           => 5,
                'blacklist_duration_minutes'         => 120,
                'block_disposable_emails'            => true,
                'blocked_domains'                    => [],
            ],
        ]);
    }
}
