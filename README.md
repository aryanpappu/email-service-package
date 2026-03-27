# SmartMailer — Multi-Provider Email Rotation for Laravel

[![Laravel](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

**SmartMailer** automatically rotates across 15+ email service providers. When one provider hits its daily or hourly limit, it silently switches to the next. Built-in spam protection, per-domain routing, and cooling periods keep your emails flowing at zero cost.

---

## Why SmartMailer?

- **Zero cost** — combine free tiers across multiple providers to get 1,400+/day for free
- **No single point of failure** — if one provider blocks or throttles you, others take over instantly
- **Spam protection** — rate limiting per recipient, IP blocking, disposable email detection
- **Per-domain routing** — different provider pools for different client domains (replaces per-domain Zoho accounts)
- **Transparent** — works with standard `Mail::send()` with zero code changes

---

## Supported Email Service Providers

### API-Based Providers (18 drivers)

| # | Provider | Driver Key | Free Tier | Notes |
|---|---|---|---|---|
| 1 | **Brevo** (Sendinblue) | `brevo` / `sendinblue` | **300/day** | Best free tier. Reliable API. |
| 2 | **Mailjet** | `mailjet` | **200/day** (6,000/month) | Good deliverability. Requires API key + secret. |
| 3 | **Resend** | `resend` | **100/day** (3,000/month) | Modern API, excellent DX. |
| 4 | **SendGrid** | `sendgrid` | **100/day** | Industry standard. Well-known deliverability. |
| 5 | **SMTP2GO** | `smtp2go` | **1,000/month** (~33/day) | Great for overflow. |
| 6 | **Elastic Email** | `elasticemail` | **100/day** | Affordable paid tiers too. |
| 7 | **SendPulse** | `sendpulse` | **15,000/month** (~500/day) | Highest free tier volume. OAuth2 auth. |
| 8 | **Mailgun** | `mailgun` | **100/day** (Flex trial) | Supports EU region. Domain-based sending. |
| 9 | **Amazon SES** | `ses` / `amazonses` | **62,000/month** (on EC2) | Cheapest paid: $0.10/1,000. No SDK needed. |
| 10 | **Postmark** | `postmark` | Paid (affordable) | Best transactional deliverability. Fast delivery. |
| 11 | **SparkPost / Bird** | `sparkpost` | Developer credits | Supports EU endpoint. High deliverability. |
| 12 | **Mailchimp Transactional** | `mandrill` | Paid (per block) | Mandrill API. Excellent deliverability. |
| 13 | **ZeptoMail** (Zoho) | `zeptomail` | Pay-as-you-go | Better Zoho alternative. No spam blocking. |
| 14 | **Netcore / Pepipost** | `netcore` / `pepipost` | **100/day** | India-focused. Good for regional deliverability. |

### SMTP-Based Providers (5 drivers)

| # | Provider | Driver Key | Free Limit | Notes |
|---|---|---|---|---|
| 15 | **Gmail SMTP** | `gmail_smtp` | **500/day** | Requires App Password (2FA must be enabled). |
| 16 | **Outlook / Hotmail SMTP** | `outlook_smtp` | ~**300/day** | Works with @outlook.com / @hotmail.com accounts. |
| 17 | **Generic SMTP** | `smtp` | Varies | Any standard SMTP server with full config. |
| 18 | **Custom SMTP** | `custom_smtp` | Varies | Zoho Mail, Hostinger, cPanel, etc. Fully configurable. |

### Total Free Capacity

| Provider | Free Limit |
|---|---|
| Brevo | 300/day |
| Mailjet | 200/day |
| SendPulse | ~500/day |
| Resend | 100/day |
| SendGrid | 100/day |
| SMTP2GO | ~33/day |
| Elastic Email | 100/day |
| Netcore | 100/day |
| Mailgun | 100/day |
| Gmail SMTP | 500/day |
| Outlook SMTP | 300/day |
| **Combined** | **~2,333/day** |

> For 50–700 emails/day: 5–6 providers fully cover peak load with redundancy built in.

---

## Installation

```bash
composer require techsolutionstuff/smart-mailer
```

Publish the config file:

```bash
php artisan vendor:publish --tag=smart-mailer-config
```

Optionally publish database migrations (only needed if `SMART_MAILER_STORAGE=database`):

```bash
php artisan vendor:publish --tag=smart-mailer-migrations
php artisan migrate
```

---

## Quick Setup

**Step 1 — Set mail driver in `.env`:**

```env
MAIL_MAILER=smart
```

**Step 2 — Add credentials for your chosen providers:**

```env
# Brevo (300/day free)
BREVO_API_KEY=your-brevo-api-key
BREVO_FROM_EMAIL=noreply@yourdomain.com
BREVO_FROM_NAME="Your App"

# Resend (100/day free)
RESEND_API_KEY=re_xxxxxxxxxxxx
RESEND_FROM_EMAIL=noreply@yourdomain.com

# Mailjet (200/day free)
MAILJET_API_KEY=your-key
MAILJET_API_SECRET=your-secret
MAILJET_FROM_EMAIL=noreply@yourdomain.com

# SendGrid (100/day free)
SENDGRID_API_KEY=SG.xxxxxxxxxx
SENDGRID_FROM_EMAIL=noreply@yourdomain.com
```

**Step 3 — Enable providers in `config/smart-mailer.php`:**

```php
'providers' => [
    'brevo_main' => [
        'driver'      => 'brevo',
        'enabled'     => true,
        'priority'    => 1,
        'api_key'     => env('BREVO_API_KEY'),
        'from_email'  => env('BREVO_FROM_EMAIL'),
        'daily_limit' => 300,
        'hourly_limit'=> 100,
    ],
    // ...
],
```

**Step 4 — Send mail exactly as before. Zero code changes needed:**

```php
Mail::to('user@example.com')->send(new WelcomeMail());
```

---

## Per-Domain Routing

Mirror your existing per-domain-per-account setup (like Zoho per client):

```php
// config/smart-mailer.php

'providers' => [

    // Client 1 has their own Brevo account
    'brevo_client1' => [
        'driver'      => 'brevo',
        'enabled'     => true,
        'priority'    => 1,
        'api_key'     => env('BREVO_CLIENT1_API_KEY'),
        'from_email'  => 'hello@client1.com',
        'from_name'   => 'Client One',
        'daily_limit' => 300,
        'hourly_limit'=> 100,
    ],

    // Client 1 fallback: Gmail App Password
    'gmail_client1' => [
        'driver'      => 'gmail_smtp',
        'enabled'     => true,
        'priority'    => 2,
        'username'    => env('GMAIL_CLIENT1_USER'),
        'password'    => env('GMAIL_CLIENT1_PASS'),
        'daily_limit' => 500,
        'hourly_limit'=> 100,
    ],
],

'domains' => [
    'client1.com' => [
        'strategy'   => 'priority',
        'providers'  => ['brevo_client1', 'gmail_client1'],
        'from_email' => 'hello@client1.com',
        'from_name'  => 'Client One',
    ],
],
```

Sending from a specific domain:

```php
// Via SmartMailer Facade
SmartMailer::domain('client1.com')
    ->to('customer@example.com')
    ->subject('Your Invoice')
    ->html($htmlContent)
    ->send();

// Standard Mail (domain resolved from app config automatically)
Mail::to('customer@example.com')->send(new InvoiceMail());
```

Wildcard domains are supported:

```php
'domains' => [
    '*.agency.com' => [
        'strategy'  => 'round_robin',
        'providers' => ['brevo_main', 'sendgrid_main'],
    ],
],
```

---

## Rotation Strategies

Configure per domain:

| Strategy | Behaviour | Best For |
|---|---|---|
| `priority` | Always use highest-priority provider with quota remaining | Default — simple and predictable |
| `round_robin` | Cycle providers in order | Spreading load evenly |
| `least_used` | Always pick provider with most remaining daily quota | Maximising free tier usage |
| `random_weighted` | Random selection weighted by remaining quota | Natural distribution |

```php
'domains' => [
    'default' => [
        'strategy' => 'least_used', // or priority, round_robin, random_weighted
    ],
],
```

---

## Spam Protection

```php
// config/smart-mailer.php
'spam_protection' => [
    'enabled'                           => true,

    // Max emails to same recipient before blocking
    'max_emails_per_recipient_per_hour' => 5,
    'max_emails_per_recipient_per_day'  => 20,

    // IP-based rate limit (prevents burst flooding from your app)
    'max_sends_per_minute'              => 10,

    // Auto-cool a provider after N consecutive failures
    'blacklist_after_failures'          => 5,
    'blacklist_duration_minutes'        => 120,

    // Block mailinator, guerrillamail, etc.
    'block_disposable_emails'           => true,

    // Custom domain blocklist
    'blocked_domains'                   => ['spam-domain.com'],
],
```

---

## Artisan Commands

```bash
# View all provider statuses, usage, cooling timers
php artisan smart-mailer:status

# View daily usage with progress bars
php artisan smart-mailer:usage
php artisan smart-mailer:usage --json           # Machine-readable

# List all configured providers and domain routing
php artisan smart-mailer:providers

# Send a test email through the pool
php artisan smart-mailer:test you@example.com
php artisan smart-mailer:test you@example.com --domain=client1.com

# Reset counters and cooling for a provider
php artisan smart-mailer:reset brevo_main
php artisan smart-mailer:reset --all            # Reset everything

# Blacklist management
php artisan smart-mailer:blacklist:add spam@evil.com
php artisan smart-mailer:blacklist:add evilsite.com --duration=1440
php artisan smart-mailer:blacklist:remove spam@evil.com
php artisan smart-mailer:blacklist:list
```

---

## Events

Listen to SmartMailer events in your `EventServiceProvider` or using `Event::listen()`:

```php
use TechSolutionStuff\SmartMailer\Events\EmailSent;
use TechSolutionStuff\SmartMailer\Events\EmailFailed;
use TechSolutionStuff\SmartMailer\Events\EmailBlocked;
use TechSolutionStuff\SmartMailer\Events\ProviderSwitched;
use TechSolutionStuff\SmartMailer\Events\ProviderExhausted;
use TechSolutionStuff\SmartMailer\Events\ProviderCooling;
use TechSolutionStuff\SmartMailer\Events\AllProvidersExhausted;

// Example: Slack alert when all providers are exhausted
Event::listen(AllProvidersExhausted::class, function ($event) {
    // Notify your team via Slack, PagerDuty, etc.
});

// Example: Log every provider switch
Event::listen(ProviderSwitched::class, function ($event) {
    Log::info("Switched from {$event->fromProvider} to {$event->toProvider}: {$event->reason}");
});
```

---

## Storage Backends

| Backend | Set via `SMART_MAILER_STORAGE=` | Best For |
|---|---|---|
| `cache` (default) | `cache` | Single server, any cache driver (file, Redis, Memcached) |
| `redis` | `redis` | Multiple queue workers — atomic `INCR`, most reliable |
| `database` | `database` | Counters must survive cache flushes; full audit trail |

```env
SMART_MAILER_STORAGE=redis
```

---

## Adding a Custom Provider

Implement the `EmailProvider` contract and register your driver:

```php
use TechSolutionStuff\SmartMailer\Drivers\BaseProvider;
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\DTOs\SendResult;

class MyCustomProvider extends BaseProvider
{
    public function getDriver(): string { return 'myprovider'; }

    public function send(MailMessage $message): SendResult
    {
        // Your sending logic here
        return SendResult::success($this->key, $messageId);
    }
}
```

Register in `AppServiceProvider::boot()`:

```php
SmartMailer::extend('myprovider', MyCustomProvider::class);
```

Then use it in config:

```php
'my_account' => [
    'driver' => 'myprovider',
    'enabled' => true,
    'priority' => 1,
    'daily_limit' => 500,
    'hourly_limit' => 100,
],
```

---

## Configuration Reference

Full config available at `config/smart-mailer.php` after publishing.

```php
return [
    'storage'  => env('SMART_MAILER_STORAGE', 'cache'), // cache | redis | database

    'defaults' => [
        'strategy'        => 'priority',   // rotation strategy
        'cooling_minutes' => 60,           // cooling period after failures
        'max_retries'     => 3,            // max provider attempts per send
        'log_channel'     => 'stack',      // Laravel log channel
    ],

    'providers' => [ /* ... */ ],          // your provider accounts
    'domains'   => [ /* ... */ ],          // domain → provider pool routing
    'spam_protection' => [ /* ... */ ],    // rate limits and blocking rules
];
```

---

## Environment Variables Reference

```env
# Storage
SMART_MAILER_STORAGE=cache
SMART_MAILER_LOG_CHANNEL=stack

# Brevo
BREVO_API_KEY=
BREVO_FROM_EMAIL=
BREVO_FROM_NAME=

# Mailjet
MAILJET_API_KEY=
MAILJET_API_SECRET=
MAILJET_FROM_EMAIL=

# Resend
RESEND_API_KEY=
RESEND_FROM_EMAIL=

# SendGrid
SENDGRID_API_KEY=
SENDGRID_FROM_EMAIL=

# SMTP2GO
SMTP2GO_API_KEY=
SMTP2GO_FROM_EMAIL=

# SendPulse
SENDPULSE_CLIENT_ID=
SENDPULSE_CLIENT_SECRET=
SENDPULSE_FROM_EMAIL=

# Elastic Email
ELASTICEMAIL_API_KEY=
ELASTICEMAIL_FROM_EMAIL=

# Amazon SES
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1

# Postmark
POSTMARK_TOKEN=

# Mailgun
MAILGUN_SECRET=
MAILGUN_DOMAIN=

# SparkPost
SPARKPOST_SECRET=

# ZeptoMail
ZEPTOMAIL_API_KEY=

# Gmail SMTP
GMAIL_USERNAME=
GMAIL_APP_PASSWORD=

# Outlook SMTP
OUTLOOK_USERNAME=
OUTLOOK_PASSWORD=

# Custom SMTP (Zoho, Hostinger, cPanel, etc.)
CUSTOM_SMTP_HOST=
CUSTOM_SMTP_PORT=587
CUSTOM_SMTP_ENCRYPTION=tls
CUSTOM_SMTP_USERNAME=
CUSTOM_SMTP_PASSWORD=
CUSTOM_SMTP_FROM_EMAIL=
```

---

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- `guzzlehttp/guzzle` ^7.0

---

## License

MIT — free to use in personal and commercial projects.
