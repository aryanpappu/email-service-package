# SmartMailer — Command Reference

Complete guide to every Artisan command, usage pattern, and real-world example.

---

## Quick Reference

```
php artisan smart-mailer:status
php artisan smart-mailer:usage
php artisan smart-mailer:providers
php artisan smart-mailer:test <email>
php artisan smart-mailer:reset <provider>
php artisan smart-mailer:blacklist:add <target>
php artisan smart-mailer:blacklist:remove <target>
php artisan smart-mailer:blacklist:list
```

---

## `smart-mailer:status`

Show real-time health, usage counters, and cooling timers for all providers.

```bash
php artisan smart-mailer:status
```

**Output:**

```
  SmartMailer Provider Status
  ─────────────────────────────────────────────────────────────────────────────────────────

 -------------- ----------- -------- ----------- ----------- ----------- ---------- -----
  Key            Driver      Status   Today       This Hour   Remaining   Failures   Cooling Until
 -------------- ----------- -------- ----------- ----------- ----------- ---------- -----
  brevo_main     brevo       OK       45/300      12/100      255         0          -
  mailjet_main   mailjet     OK       30/200      8/80        170         0          -
  resend_main    resend      LOW      95/100      35/40       5           0          -
  sendgrid_main  sendgrid    COOLING  100/100     40/40       0           3          2024-01-15 14:30:00
  smtp2go_main   smtp2go     OK       10/33       3/15        23          0          -
 -------------- ----------- -------- ----------- ----------- ----------- ---------- -----

  Total sent today: 280 | Remaining (limited providers): 453
```

**Status labels:**
- `OK` — Provider healthy, has remaining quota
- `LOW` — Under 10 emails remaining today
- `EXHAUSTED` — Daily limit reached, will not be used until midnight
- `COOLING` — In cooldown period after failures (auto-recovers)
- `DISABLED` — Manually disabled in config (`'enabled' => false`)

**Filter to a single provider:**

```bash
php artisan smart-mailer:status --provider=brevo_main
```

---

## `smart-mailer:usage`

Show daily usage with visual progress bars and percentage breakdown.

```bash
php artisan smart-mailer:usage
```

**Output:**

```
  Daily Usage Report

 -------------- ------------ ------------- ----------- ---------------------------------- -----------
  Provider       Sent Today   Daily Limit   Remaining   Usage                              Total Ever
 -------------- ------------ ------------- ----------- ---------------------------------- -----------
  brevo_main     45           300           255         15.0% ██░░░░░░░░                 1,245
  mailjet_main   30           200           170         15.0% ██░░░░░░░░                 876
  resend_main    95           100           5           95.0% █████████░                 432
  sendgrid_main  100          100           0           100.0% ██████████                 2,100
  smtp2go_main   10           33            23          30.3% ███░░░░░░░                 210
 -------------- ------------ ------------- ----------- ---------------------------------- -----------

  Combined: 280/733 (38.2% used)
```

**Filter to one provider:**

```bash
php artisan smart-mailer:usage --provider=brevo_main
```

**Machine-readable JSON output (for monitoring scripts / cron):**

```bash
php artisan smart-mailer:usage --json
```

```json
[
    {
        "key": "brevo_main",
        "driver": "brevo",
        "sent_today": 45,
        "sent_this_hour": 12,
        "sent_total": 1245,
        "daily_limit": 300,
        "hourly_limit": 100,
        "remaining_today": 255,
        "remaining_this_hour": 88,
        "is_cooling": false
    }
]
```

**Use in a monitoring cron (e.g., alert if combined remaining < 50):**

```bash
# In a shell script or Laravel scheduler
php artisan smart-mailer:usage --json | jq '[.[].remaining_today] | add'
```

---

## `smart-mailer:providers`

List all configured providers, their drivers, limits, and domain routing table.

```bash
php artisan smart-mailer:providers
```

**Output:**

```
  Configured Providers

 ------------------- ----------- --------- ---------- ------------- -------------- -------------------
  Key                 Driver      Enabled   Priority   Daily Limit   Hourly Limit   From Email
 ------------------- ----------- --------- ---------- ------------- -------------- -------------------
  brevo_main          brevo       Yes       1          300           100            noreply@example.com
  mailjet_main        mailjet     Yes       2          200           80             noreply@example.com
  resend_main         resend      Yes       3          100           40             noreply@example.com
  sendgrid_main       sendgrid    Yes       4          100           40             noreply@example.com
  gmail_main          gmail_smtp  No        6          500           100            (from message)
 ------------------- ----------- --------- ---------- ------------- -------------- -------------------

  Domain Routing

 ------------- ----------- ------------------------------------ --------------------------
  Domain        Strategy    Providers                            From Email
 ------------- ----------- ------------------------------------ --------------------------
  default       priority    brevo_main, mailjet_main, resend_m…  noreply@example.com
  client1.com   priority    brevo_client1, gmail_client1          hello@client1.com
 ------------- ----------- ------------------------------------ --------------------------

  Registered Drivers: smtp, brevo, sendinblue, sendgrid, mailgun, resend, mailjet, ...
```

---

## `smart-mailer:test`

Send a real test email through the provider pool to verify configuration.

```bash
php artisan smart-mailer:test you@example.com
```

**Output:**

```
Sending test email to [you@example.com]...

  ✓ Email sent successfully!
  Provider: brevo_main
  Message ID: <201701011234.56789@smtp.brevo.com>
```

**Test through a specific domain's provider pool:**

```bash
php artisan smart-mailer:test client@example.com --domain=client1.com
```

**Custom subject:**

```bash
php artisan smart-mailer:test you@example.com --subject="Deployment Verification"
```

**Typical use cases:**
- After setting up a new provider: verify credentials work
- After deployment: confirm email is flowing
- Debug a domain routing issue: `--domain=client1.com`

---

## `smart-mailer:reset`

Reset usage counters and cooling state for a provider so it can send again immediately.

**Reset a single provider:**

```bash
php artisan smart-mailer:reset brevo_main
```

```
Reset all counters and cooling for provider [brevo_main]? (yes/no) [no]: yes
Provider [brevo_main] has been reset.
```

**Reset all providers at once (no confirmation prompt):**

```bash
php artisan smart-mailer:reset dummy --all
```

```
  Reset: brevo_main
  Reset: mailjet_main
  Reset: resend_main
  Reset: sendgrid_main
All providers reset successfully.
```

**When to use:**
- A provider was mistakenly reported as exhausted (e.g., after changing API keys)
- Manual testing during development
- After switching to a new billing period with refreshed free-tier quotas
- After resolving a spam issue that caused cooling

**Warning:** Resetting does not change what was actually sent. Counters are tracking state only — resetting doesn't give you more free-tier emails, it just allows SmartMailer to try the provider again.

---

## `smart-mailer:blacklist:add`

Block an email address or domain from receiving emails.

**Block a specific email address permanently:**

```bash
php artisan smart-mailer:blacklist:add spam@evil.com
```

```
Added [spam@evil.com] to blacklist permanently.
```

**Block a domain permanently:**

```bash
php artisan smart-mailer:blacklist:add evil-domain.com
```

**Temporary block — expires after N minutes:**

```bash
# Block for 24 hours (1440 minutes)
php artisan smart-mailer:blacklist:add bounce@example.com --duration=1440

# Block for 1 hour
php artisan smart-mailer:blacklist:add aggressive@user.com --duration=60
```

**When to use:**
- A customer requests to unsubscribe — block their email
- A domain is generating spam complaints — block the domain
- A user is flooding your contact form — temporary block their email
- You received a hard bounce — block to avoid further attempts

---

## `smart-mailer:blacklist:remove`

Remove an email address or domain from the blacklist.

```bash
php artisan smart-mailer:blacklist:remove spam@evil.com
php artisan smart-mailer:blacklist:remove evil-domain.com
```

```
Removed [spam@evil.com] from blacklist.
```

---

## `smart-mailer:blacklist:list`

View all currently blacklisted emails and domains.

```bash
php artisan smart-mailer:blacklist:list
```

**Output:**

```
 ----------------------------- ------------- --------------------- ---------------------
  Target                        Type          Added At              Expires At
 ----------------------------- ------------- --------------------- ---------------------
  spam@evil.com                 Permanent     2024-01-15 10:00:00   -
  evil-domain.com               Permanent     2024-01-15 10:05:00   -
  bounce@customer.com           Temporary     2024-01-15 12:00:00   2024-01-16 12:00:00
 ----------------------------- ------------- --------------------- ---------------------

3 entries in blacklist.
```

---

## Using SmartMailer in Code

### Standard Laravel Mail (zero code change)

```php
// Works exactly as before — SmartMailer handles routing automatically
Mail::to('user@example.com')->send(new WelcomeMail());

// Queue it
Mail::to('user@example.com')->queue(new InvoiceMail($invoice));

// Multiple recipients
Mail::to('a@example.com')
    ->cc('b@example.com')
    ->bcc('admin@example.com')
    ->send(new OrderMail($order));
```

### SmartMailer Facade (fluent API)

```php
use TechSolutionStuff\SmartMailer\Facades\SmartMailer;

// Simple send
SmartMailer::to('user@example.com')
    ->from('noreply@yourdomain.com', 'Your App')
    ->subject('Welcome!')
    ->html('<h1>Hello</h1>')
    ->text('Hello')
    ->send();

// Route to a specific client domain pool
SmartMailer::domain('client1.com')
    ->to('customer@example.com')
    ->subject('Your Invoice #1234')
    ->html($htmlContent)
    ->send();

// Check status programmatically
$statuses = SmartMailer::status();
foreach ($statuses as $key => $status) {
    echo "{$key}: {$status->remainingToday} remaining today\n";
}

// Reset a provider
SmartMailer::reset('brevo_main');
```

### Direct MailMessage DTO

```php
use TechSolutionStuff\SmartMailer\DTOs\MailMessage;
use TechSolutionStuff\SmartMailer\Facades\SmartMailer;

$message = new MailMessage(
    fromEmail: 'orders@client1.com',
    fromName:  'Client One Orders',
    subject:   'Order Confirmation',
    htmlBody:  view('emails.order-confirmation', ['order' => $order])->render(),
    textBody:  "Your order #{$order->id} has been confirmed.",
    domain:    'client1.com',
);

$message
    ->to($order->customer_email, $order->customer_name)
    ->cc('accounts@client1.com')
    ->replyTo('support@client1.com', 'Support Team')
    ->attach('/path/to/invoice.pdf', 'Invoice.pdf', 'application/pdf');

$result = SmartMailer::send($message);

if ($result->success) {
    Log::info("Order email sent via {$result->providerKey}", ['id' => $result->messageId]);
}
```

### Listening to Events

```php
// In EventServiceProvider or a dedicated listener

use TechSolutionStuff\SmartMailer\Events\AllProvidersExhausted;
use TechSolutionStuff\SmartMailer\Events\ProviderSwitched;
use TechSolutionStuff\SmartMailer\Events\EmailBlocked;

// Alert when no providers left
Event::listen(AllProvidersExhausted::class, function ($event) {
    // Send a Slack alert, trigger PagerDuty, etc.
    \Slack::message("⚠️ SmartMailer: All providers exhausted for domain [{$event->domain}]");
});

// Log provider switches
Event::listen(ProviderSwitched::class, function ($event) {
    Log::warning("Provider switched: {$event->fromProvider} → {$event->toProvider} ({$event->reason})");
});

// Track blocked emails
Event::listen(EmailBlocked::class, function ($event) {
    Log::info("Email blocked for [{$event->identifier}]: {$event->reason}");
});
```

### Laravel Scheduler — Daily Usage Report

```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // JSON usage dump at midnight for monitoring
    $schedule->command('smart-mailer:usage --json')
        ->dailyAt('23:55')
        ->appendOutputTo(storage_path('logs/smart-mailer-daily.log'));

    // Reset providers if you want clean daily counters in DB storage
    // (Cache/Redis storage resets automatically at midnight via TTL)
    // $schedule->command('smart-mailer:reset --all')->dailyAt('00:01');
}
```

---

## Troubleshooting

### Email not sending

```bash
# 1. Check provider status
php artisan smart-mailer:status

# 2. Send a test to confirm credentials work
php artisan smart-mailer:test your@email.com

# 3. If provider is EXHAUSTED, check if limits are correct in config
php artisan smart-mailer:providers

# 4. If provider is COOLING, wait or reset manually
php artisan smart-mailer:reset brevo_main
```

### "All providers exhausted" exception

```bash
# Check which providers are available
php artisan smart-mailer:status

# If all are exhausted, either:
# (a) Reset them (if counters are wrong)
php artisan smart-mailer:reset --all

# (b) Add more providers to your config
# (c) Increase limits (requires matching free tier limits)
```

### Verify a specific domain is routing correctly

```bash
php artisan smart-mailer:test you@email.com --domain=client1.com
php artisan smart-mailer:providers  # Check domain routing table
```

### Provider is stuck in COOLING

```bash
# Reset just that provider
php artisan smart-mailer:reset brevo_main

# Or wait — cooling expires automatically (default 60 minutes)
php artisan smart-mailer:status --provider=brevo_main
# Shows "Cooling Until" timestamp
```

### Check if an email is blacklisted

```bash
php artisan smart-mailer:blacklist:list
# Then manually check if the email appears
```

---

## Environment Variables Cheatsheet

```env
# Core
MAIL_MAILER=smart
SMART_MAILER_STORAGE=cache          # cache | redis | database
SMART_MAILER_LOG_CHANNEL=stack
SMART_MAILER_REDIS_CONNECTION=default

# Brevo (300/day free)
BREVO_API_KEY=
BREVO_FROM_EMAIL=
BREVO_FROM_NAME=

# Mailjet (200/day free)
MAILJET_API_KEY=
MAILJET_API_SECRET=
MAILJET_FROM_EMAIL=

# Resend (100/day free)
RESEND_API_KEY=

# SendGrid (100/day free)
SENDGRID_API_KEY=

# SMTP2GO (1000/month free)
SMTP2GO_API_KEY=

# SendPulse (15000/month free)
SENDPULSE_CLIENT_ID=
SENDPULSE_CLIENT_SECRET=

# Mailgun
MAILGUN_SECRET=
MAILGUN_DOMAIN=

# Amazon SES
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1

# Gmail SMTP (500/day — needs App Password)
GMAIL_USERNAME=
GMAIL_APP_PASSWORD=

# Custom SMTP (Zoho, Hostinger, cPanel, etc.)
CUSTOM_SMTP_HOST=smtp.zoho.com
CUSTOM_SMTP_PORT=587
CUSTOM_SMTP_ENCRYPTION=tls
CUSTOM_SMTP_USERNAME=
CUSTOM_SMTP_PASSWORD=
CUSTOM_SMTP_FROM_EMAIL=
```
