<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer;

use Illuminate\Cache\Repository as Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use TechSolutionStuff\SmartMailer\Console\BlacklistAddCommand;
use TechSolutionStuff\SmartMailer\Console\BlacklistListCommand;
use TechSolutionStuff\SmartMailer\Console\BlacklistRemoveCommand;
use TechSolutionStuff\SmartMailer\Console\ProvidersCommand;
use TechSolutionStuff\SmartMailer\Console\ResetCommand;
use TechSolutionStuff\SmartMailer\Console\StatusCommand;
use TechSolutionStuff\SmartMailer\Console\TestCommand;
use TechSolutionStuff\SmartMailer\Console\UsageCommand;
use TechSolutionStuff\SmartMailer\Contracts\UsageStorage;
use TechSolutionStuff\SmartMailer\Services\DomainResolver;
use TechSolutionStuff\SmartMailer\Services\ProviderFactory;
use TechSolutionStuff\SmartMailer\Services\ProviderPool;
use TechSolutionStuff\SmartMailer\Services\SpamGuard;
use TechSolutionStuff\SmartMailer\Services\UsageTracker;
use TechSolutionStuff\SmartMailer\Storage\CacheStorage;
use TechSolutionStuff\SmartMailer\Storage\DatabaseStorage;
use TechSolutionStuff\SmartMailer\Storage\RedisStorage;
use TechSolutionStuff\SmartMailer\Transport\SmartMailerTransport;

class SmartMailerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/smart-mailer.php', 'smart-mailer');

        $this->app->singleton(UsageStorage::class, function ($app): UsageStorage {
            $config  = $app['config']->get('smart-mailer');
            $storage = $config['storage'] ?? 'cache';

            return match ($storage) {
                // SEC-6: Use dedicated Redis connection to avoid key collisions with sessions/cache
                'redis'    => new RedisStorage($app['redis']->connection(
                    $config['redis_connection'] ?? 'default'
                )),
                'database' => new DatabaseStorage($app['db']->connection()),
                default    => new CacheStorage($app->make(Cache::class)),
            };
        });

        $this->app->singleton(UsageTracker::class, function ($app): UsageTracker {
            return new UsageTracker(
                $app->make(UsageStorage::class),
                $app['config']->get('smart-mailer'),
            );
        });

        $this->app->singleton(SpamGuard::class, function ($app): SpamGuard {
            return new SpamGuard(
                $app->make(Cache::class),
                $app['config']->get('smart-mailer'),
            );
        });

        $this->app->singleton(ProviderFactory::class, function ($app): ProviderFactory {
            return new ProviderFactory($app['config']->get('smart-mailer'));
        });

        $this->app->singleton(DomainResolver::class, function ($app): DomainResolver {
            return new DomainResolver($app['config']->get('smart-mailer'));
        });

        $this->app->singleton(ProviderPool::class, function ($app): ProviderPool {
            return new ProviderPool(
                $app->make(ProviderFactory::class),
                $app->make(UsageTracker::class),
                $app->make(DomainResolver::class),
                $app->make(SpamGuard::class),
                $app->make(Cache::class),
                $app['config']->get('smart-mailer'),
            );
        });

        $this->app->singleton(SmartMailerManager::class, function ($app): SmartMailerManager {
            return new SmartMailerManager(
                $app->make(ProviderPool::class),
                $app->make(ProviderFactory::class),
                $app['config']->get('smart-mailer'),
            );
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/smart-mailer.php' => config_path('smart-mailer.php'),
        ], 'smart-mailer-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'smart-mailer-migrations');

        // Load migrations automatically (optional: let user decide via publish)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                StatusCommand::class,
                ResetCommand::class,
                TestCommand::class,
                ProvidersCommand::class,
                BlacklistAddCommand::class,
                BlacklistRemoveCommand::class,
                BlacklistListCommand::class,
                UsageCommand::class,
            ]);
        }

        // Register the 'smart' mail driver
        Mail::extend('smart', function (array $config) {
            $domain = $config['domain'] ?? null;
            return new SmartMailerTransport(
                $this->app->make(ProviderPool::class),
                $domain,
            );
        });
    }
}
