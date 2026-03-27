<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking Storage Driver
    |--------------------------------------------------------------------------
    | Determines how send counters and cooling states are persisted.
    |
    | Options:
    |   "cache"    — Uses Laravel Cache with atomic locks. Works with any
    |                cache driver (file, redis, memcached). Default.
    |   "redis"    — Direct Redis INCR commands. Most reliable for multiple
    |                queue workers running concurrently.
    |   "database" — Stores in database tables. Survives cache flushes.
    |                Run: php artisan migrate  (after vendor:publish)
    */
    'storage' => env('SMART_MAILER_STORAGE', 'cache'),

    /*
    |--------------------------------------------------------------------------
    | Global Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // Rotation strategy: priority | round_robin | least_used | random_weighted
        'strategy'                    => 'priority',

        // Minutes to cool a provider after hitting failure threshold
        'cooling_minutes'             => 60,

        // Number of consecutive send failures before a provider enters cooling
        // Separate from spam_protection.blacklist_after_failures (which is for recipient blocking)
        'max_failures_before_cooling' => 5,

        // Max provider attempts per send before throwing AllProvidersExhaustedException
        'max_retries'                 => 3,

        // Log channel to use for SmartMailer messages (stack, daily, single, etc.)
        'log_channel'                 => env('SMART_MAILER_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connection (only used when storage = "redis")
    |--------------------------------------------------------------------------
    | Use a dedicated Redis connection to avoid key collisions with sessions,
    | cache, and queues. Configure in config/database.php under 'redis'.
    | If not set, falls back to the 'default' Redis connection.
    */
    'redis_connection' => env('SMART_MAILER_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Email Providers
    |--------------------------------------------------------------------------
    | Each entry has a unique key used for routing and tracking.
    | Lower priority number = tried first (1 = highest priority).
    |
    | Required fields for all providers:
    |   driver        — See driver list below
    |   enabled       — true/false
    |   priority      — integer (1 = first)
    |   daily_limit   — int (max sends per calendar day)
    |   hourly_limit  — int (max sends per clock hour)
    |
    | Optional fields:
    |   from_email  — override sender email for this provider
    |   from_name   — override sender name for this provider
    |
    | Supported drivers:
    |   smtp, gmail_smtp, outlook_smtp, custom_smtp
    |   brevo (sendinblue), sendgrid, mailgun, resend, mailjet,
    |   smtp2go, elasticemail, ses (amazonses), sendpulse,
    |   postmark, sparkpost, mandrill, zeptomail, netcore (pepipost)
    */
    'providers' => [

        // ── Free Tier: 300/day ─────────────────────────────────────────────
        'brevo_main' => [
            'driver'      => 'brevo',
            'enabled'     => (bool) env('BREVO_API_KEY'),
            'priority'    => 1,
            'api_key'     => env('BREVO_API_KEY'),
            'from_email'  => env('BREVO_FROM_EMAIL'),
            'from_name'   => env('BREVO_FROM_NAME'),
            'daily_limit' => 300,
            'hourly_limit'=> 100,
        ],

        // ── Free Tier: 200/day ─────────────────────────────────────────────
        'mailjet_main' => [
            'driver'      => 'mailjet',
            'enabled'     => (bool) env('MAILJET_API_KEY'),
            'priority'    => 2,
            'api_key'     => env('MAILJET_API_KEY'),
            'api_secret'  => env('MAILJET_API_SECRET'),
            'from_email'  => env('MAILJET_FROM_EMAIL'),
            'from_name'   => env('MAILJET_FROM_NAME'),
            'daily_limit' => 200,
            'hourly_limit'=> 80,
        ],

        // ── Free Tier: 100/day ─────────────────────────────────────────────
        'resend_main' => [
            'driver'      => 'resend',
            'enabled'     => (bool) env('RESEND_API_KEY'),
            'priority'    => 3,
            'api_key'     => env('RESEND_API_KEY'),
            'from_email'  => env('RESEND_FROM_EMAIL'),
            'from_name'   => env('RESEND_FROM_NAME'),
            'daily_limit' => 100,
            'hourly_limit'=> 40,
        ],

        // ── Free Tier: 100/day ─────────────────────────────────────────────
        'sendgrid_main' => [
            'driver'      => 'sendgrid',
            'enabled'     => (bool) env('SENDGRID_API_KEY'),
            'priority'    => 4,
            'api_key'     => env('SENDGRID_API_KEY'),
            'from_email'  => env('SENDGRID_FROM_EMAIL'),
            'from_name'   => env('SENDGRID_FROM_NAME'),
            'daily_limit' => 100,
            'hourly_limit'=> 40,
        ],

        // ── Free Tier: ~33/day (1000/month) ───────────────────────────────
        'smtp2go_main' => [
            'driver'      => 'smtp2go',
            'enabled'     => (bool) env('SMTP2GO_API_KEY'),
            'priority'    => 5,
            'api_key'     => env('SMTP2GO_API_KEY'),
            'from_email'  => env('SMTP2GO_FROM_EMAIL'),
            'from_name'   => env('SMTP2GO_FROM_NAME'),
            'daily_limit' => 33,
            'hourly_limit'=> 15,
        ],

        // ── Free Tier: 500/day (Gmail App Password) ────────────────────────
        'gmail_main' => [
            'driver'      => 'gmail_smtp',
            'enabled'     => false, // Enable after setting credentials
            'priority'    => 6,
            'username'    => env('GMAIL_USERNAME'),
            'password'    => env('GMAIL_APP_PASSWORD'),
            'from_email'  => env('GMAIL_USERNAME'),
            'from_name'   => env('GMAIL_FROM_NAME'),
            'daily_limit' => 500,
            'hourly_limit'=> 100,
        ],

        // ── Free Tier: 15,000/month ────────────────────────────────────────
        'sendpulse_main' => [
            'driver'        => 'sendpulse',
            'enabled'       => (bool) env('SENDPULSE_CLIENT_ID'),
            'priority'      => 7,
            'client_id'     => env('SENDPULSE_CLIENT_ID'),
            'client_secret' => env('SENDPULSE_CLIENT_SECRET'),
            'from_email'    => env('SENDPULSE_FROM_EMAIL'),
            'from_name'     => env('SENDPULSE_FROM_NAME'),
            'daily_limit'   => 500,
            'hourly_limit'  => 100,
        ],

        // ── Free Tier: 100/day ─────────────────────────────────────────────
        'elasticemail_main' => [
            'driver'      => 'elasticemail',
            'enabled'     => false,
            'priority'    => 8,
            'api_key'     => env('ELASTICEMAIL_API_KEY'),
            'from_email'  => env('ELASTICEMAIL_FROM_EMAIL'),
            'daily_limit' => 100,
            'hourly_limit'=> 40,
        ],

        // ── Example: Custom SMTP (Zoho, Hostinger, etc.) ───────────────────
        'custom_smtp_1' => [
            'driver'      => 'custom_smtp',
            'enabled'     => false,
            'priority'    => 20,
            'host'        => env('CUSTOM_SMTP_HOST', 'smtp.zoho.com'),
            'port'        => env('CUSTOM_SMTP_PORT', 587),
            'encryption'  => env('CUSTOM_SMTP_ENCRYPTION', 'tls'),
            'username'    => env('CUSTOM_SMTP_USERNAME'),
            'password'    => env('CUSTOM_SMTP_PASSWORD'),
            'from_email'  => env('CUSTOM_SMTP_FROM_EMAIL'),
            'from_name'   => env('CUSTOM_SMTP_FROM_NAME'),
            'daily_limit' => 100,
            'hourly_limit'=> 30,
        ],

        /*
        |----------------------------------------------------------------------
        | Multi-Domain / Multi-Account Pattern
        |----------------------------------------------------------------------
        | For per-client accounts, duplicate entries with different keys.
        | Example: client1 has their own Brevo account:
        |
        | 'brevo_client1' => [
        |     'driver'    => 'brevo',
        |     'enabled'   => true,
        |     'priority'  => 1,
        |     'api_key'   => env('BREVO_CLIENT1_API_KEY'),
        |     'from_email'=> 'hello@client1.com',
        |     'from_name' => 'Client One',
        |     'daily_limit' => 300,
        |     'hourly_limit'=> 100,
        | ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Routing
    |--------------------------------------------------------------------------
    | Map domain names to a specific provider pool and rotation strategy.
    | 'default' is used when no domain match is found.
    |
    | Per-domain keys:
    |   strategy   — Override the rotation strategy for this domain
    |   providers  — Ordered list of provider keys assigned to this domain
    |   from_email — Default from address for emails from this domain
    |   from_name  — Default from name
    |
    | Wildcard domains supported: '*.example.com'
    */
    'domains' => [

        'default' => [
            'strategy'   => 'priority',
            'providers'  => ['brevo_main', 'mailjet_main', 'resend_main', 'sendgrid_main', 'smtp2go_main'],
            'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'from_name'  => env('MAIL_FROM_NAME', 'My Application'),
        ],

        /*
        | Example per-domain routing:
        |
        | 'client1.com' => [
        |     'strategy'   => 'priority',
        |     'providers'  => ['brevo_client1', 'gmail_client1'],
        |     'from_email' => 'hello@client1.com',
        |     'from_name'  => 'Client One',
        | ],
        |
        | '*.agency.com' => [
        |     'strategy'   => 'round_robin',
        |     'providers'  => ['brevo_main', 'sendgrid_main'],
        |     'from_email' => 'support@agency.com',
        |     'from_name'  => 'Agency Support',
        | ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Protection
    |--------------------------------------------------------------------------
    */
    'spam_protection' => [

        // Master switch
        'enabled' => true,

        // Max emails to the same recipient per hour
        'max_emails_per_recipient_per_hour' => 5,

        // Max emails to the same recipient per day
        'max_emails_per_recipient_per_day'  => 20,

        // Max sends from the same IP per minute (prevents burst flooding)
        'max_sends_per_minute' => 10,

        // Auto-cool provider after this many consecutive send failures
        'blacklist_after_failures' => 5,

        // Minutes to auto-blacklist a provider after hitting failure threshold
        'blacklist_duration_minutes' => 120,

        // Block well-known disposable email services (mailinator, guerrillamail, etc.)
        'block_disposable_emails' => true,

        // Additional domains to always block (e.g. competitors, known spam domains)
        'blocked_domains' => [],
    ],

];
