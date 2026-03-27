<?php

declare(strict_types=1);

namespace TechSolutionStuff\SmartMailer\Facades;

use Illuminate\Support\Facades\Facade;
use TechSolutionStuff\SmartMailer\SmartMailerManager;

/**
 * @method static \TechSolutionStuff\SmartMailer\SmartMailerManager domain(string $domain)
 * @method static \TechSolutionStuff\SmartMailer\DTOs\SendResult send(\TechSolutionStuff\SmartMailer\DTOs\MailMessage $message)
 * @method static \TechSolutionStuff\SmartMailer\SmartMailerManager to(string $email, string $name = '')
 * @method static \TechSolutionStuff\SmartMailer\SmartMailerManager subject(string $subject)
 * @method static \TechSolutionStuff\SmartMailer\SmartMailerManager html(string $html)
 * @method static \TechSolutionStuff\SmartMailer\SmartMailerManager text(string $text)
 * @method static array status()
 * @method static void reset(string $providerKey)
 * @method static void extend(string $driver, string $class)
 *
 * @see \TechSolutionStuff\SmartMailer\SmartMailerManager
 */
class SmartMailer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmartMailerManager::class;
    }
}
