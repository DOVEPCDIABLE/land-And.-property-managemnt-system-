# citiESTATE — Real Estate Business Ecosystem

PHP **8.4** / Laravel 13 / MySQL foundation for the ecosystem described in `real-eastate-business.txt`. The responsive Blade interface uses local CSS and needs no frontend build.

## Implemented

- Public editorial homepage at `/`, with local photography/fonts, a reduced-motion-aware architectural video hero, responsive search/filter forms, concept property/detail pages, locally saved favourites and comparisons, journal pages, and real inquiry capture.
- Public requests are stored in `public_inquiries` and reviewed by platform administrators at `/public-inquiries`. Property listings are clearly labelled concepts. No fabricated professionals, testimonials, verifications, market statistics or live availability are presented. Automated valuations, alert delivery, marketplace publishing and directory/referral workflows remain planned.

- Login/logout, email verification, password reset, throttling, encrypted database sessions and password-hash session invalidation.
- Scoped multi-role memberships, enabled-module checks, active/disabled accounts and memberships, organisation isolation, account creation and access editing, and audit records. Platform administrators are created through the CLI; the web interface cannot grant that role or change its accounts.
- Contextual personal, organisation and platform workspaces, with grouped sidebars for the 19 documented role templates and controlled workspace switching.
- Personal notification inbox, scoped recipient selection, read state, queued email and in-app delivery. Dismissible toast feedback supports success, error, warning and information messages; success messages auto-dismiss with hover/focus pause.
- Scheduled follow-ups, UTC storage with Africa/Lagos input/display, cancellation, retries and terminal failure state. Agents currently schedule personal reminders; agency administrators can schedule reminders for their agency users.
- ZeptoMail API transport, including queued verification and reset emails. The default `log` mailer keeps development local.
- Manual payment recording without moving money. Optional Paystack and Flutterwave v3 hosted checkout; Eversend Merchant API NGN temporary virtual accounts.
- Signed webhooks, persisted event deduplication, queued server verification, exact amount/currency/reference matching, row-locked payment transitions and unique provider transaction IDs. Browser redirects never confirm payment.
- A roadmap showing all 16 modules and the six flagship features. The business workflows are planned, not complete.

## Local setup

Requirements: PHP 8.4 with PDO MySQL, mbstring, OpenSSL, cURL, DOM/XML and standard Laravel extensions; Composer; MySQL 8 or compatible MariaDB. SQLite is used for isolated tests only.

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database named `real_estate_ecosystem`, then set `DB_*` in `.env`. Use a dedicated database user outside local development.

```sh
php artisan migrate --seed
php artisan ecosystem:admin you@example.com --name="Your Name"
php artisan ecosystem:admin agency@example.com --name="Agency Owner" --agency="Your Agency"
```

Both commands prompt for a hidden password (12+ characters with mixed case and numbers). They do not overwrite existing accounts. There are no default accounts or passwords. Repeat the agency command with a different email and agency name to provision another agency.

Start the app and, in separate terminals, its worker and scheduler:

```sh
php artisan serve --host=127.0.0.1 --port=8084
php artisan queue:work database --queue=payments,notifications,default --tries=5 --timeout=60
php artisan schedule:work
```

Visit **http://127.0.0.1:8084**. A worker is required to send verification and reset emails. With `MAIL_MAILER=log`, find local emails in `storage/logs/laravel.log`.

### This machine: XAMPP + Herd

The existing XAMPP Apache PHP is 8.2 and cannot run this application. PHP 8.4 is installed at `/Users/abayomidaniel/Library/Application Support/Herd/bin/php84`. Its normal startup config references missing Herd files. Use `-n` to bypass that broken config without changing machine settings; the needed extensions are built into this binary.

The local `.env` is configured for XAMPP's database socket, and `real_estate_ecosystem` has been migrated and seeded. Administrator accounts are provisioned separately with the command below.

```sh
PHP84='/Users/abayomidaniel/Library/Application Support/Herd/bin/php84'
"$PHP84" -n artisan ecosystem:admin you@example.com --name="Your Name"
"$PHP84" -n artisan ecosystem:admin agency@example.com --agency="Your Agency"
"$PHP84" -n artisan queue:work database --queue=payments,notifications,default --tries=5 --timeout=60
```

For a local web server with the same PHP 8.4 binary, run from `public/` in a separate terminal:

```sh
cd public
'/Users/abayomidaniel/Library/Application Support/Herd/bin/php84' -n -S 127.0.0.1:8084 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Run the scheduler once per minute with the same PHP binary (`-n artisan schedule:run` from the project root). For development, `-n artisan schedule:work` is also available; its subprocess configuration should be checked on this machine because of the broken default PHP configuration.

Point any Apache/Nginx/Herd virtual host at **`public/`**, using PHP 8.4. The root `.htaccess` blocks direct source-tree access under XAMPP. Do not expose the project root or serve it with PHP 8.2.

## Accounts, workspaces and access

A single account can have a personal workspace and several organisation memberships. Each membership can have multiple roles; only roles in the selected workspace contribute permissions. The sidebar groups those modules by audience, current permissions and workspace entitlements. The workspace switcher is at `/workspaces`.

`config/workspaces.php` defines all 19 requested role templates, the legacy client alias, action permissions (view/create/edit/delete/assign/approve/publish/export/manage), scope limits, module status and profession-specific provider additions. The old `users.role_id` and `agency_id` columns are migration/bootstrap hints only, **not an authorisation source**. New role changes use membership role assignments.

- Personal roles: buyer, owner, tenant, investor and service provider can coexist. These roles never self-grant platform or organisation administration. Selecting a profession does not verify credentials.
- Platform roles: super administrator, operations/compliance and support. Staff roles can be assigned to an existing account in Users & Access. Super administrator assignments remain CLI-only.
- Organisation roles: agency admin, branch manager, agent, marketing/CRM, property manager, accountant, HR/payroll, developer admin, project manager, contractor and auditor, plus eligible personal roles used in an organisation.
- Every record query first restricts `workspace_id`, then applies its action-specific own, assigned, team, branch, organisation or platform scope. A membership scope is a maximum; it does not expand a role’s grant. Missing team/branch assignments fail closed. Export requires both a role grant and the explicit membership flag; export workflows are not implemented yet.
- Platform administration can manage account metadata and organisation status, but it does not automatically read another organisation’s payment, client, document or operational records. This foundation has no cross-organisation impersonation or exceptional-access bypass.
- Organisation admins update membership access without changing another account’s global credentials, email or other memberships. Existing accounts are added by email, avoiding duplicate identities. New public registration creates a personal workspace and requires email verification.
- Modules disabled in workspace settings are hidden and blocked on the server. Shared account/security functions and public browsing remain core functions. Planned business modules have clearly labelled roadmap pages, not simulated operational data.
- Account pages support personal roles/profile, password changes with current-password confirmation, database-session listing/revocation and in-app/email preferences. Two-factor authentication is explicitly planned, not enabled.
- Workspace switches, role assignments, organisation changes, support lookups, account security changes and record mutations are audited. Notification inboxes are workspace-scoped; old account-level notifications remain personal account events. Deferred reminders are cancelled when relevant membership access is revoked.

Create an organisation from **Platform → Organisations** with an existing account as administrator. Then switch into that organisation, add branches/teams if needed, and add existing accounts through **Users & Access**. Administrators can enable modules in **Workspace settings**. Selecting a planned module only exposes its roadmap; CRM, tenancy, construction and other domain workflows still require implementation.

The workspace migration preserves existing accounts and provisions memberships from their legacy roles. Historic records are assigned by organisation or user where this can be determined; records without a safe mapping remain outside accessible workspace queries and need operator review. The seed command synchronises role grants and scopes idempotently:

```sh
php artisan db:seed --class=AccessControlSeeder
```

## Super-admin system settings

Open **Administration → System settings** (`/system/settings`) in the platform workspace. Organisation admins, support, compliance and personal workspaces cannot read or update these global settings.

The UI controls the platform/company name, tagline, public support contacts, light/dark logo uploads, favicon, accessible brand colours, default/enabled currencies, number separators, display timezone, validation locale, payment limits, gateway currency policy and processing switch. API secrets and verified sending domains remain server-managed. Logo uploads accept PNG/JPEG/WebP, with dimension/size limits; SVG uploads are rejected. Settings edits are version-checked and audited.

Currency metadata starts with **165 ISO 4217 entries with defined numeric minor units**, from [SIX List One](https://www.six-group.com/dam/download/financial-information/data-center/iso-currrency/lists/list-one.xml), published 17 September 2026. Other codes and explicit precision can be added in settings. Standard precision cannot be edited; custom precision becomes fixed once used in a payment. Disabling a currency affects new entries, not historical display or verification.

Amounts are integer minor units, using exact decimal arithmetic from the framework's installed Brick Math dependency. Every payment stores its original currency and minor-unit exponent. A changed default **never relabels or rewrites existing money**. Gateway adapters have narrower supported currencies and merchant-market requirements; in particular, the Eversend temporary-account adapter remains NGN-only, and [Paystack's XOF API](https://paystack.com/docs/api/) uses ×100 wire amounts even though internal XOF has zero decimal places.

Property samples keep their original NGN denomination. A preferred display currency is used only with an explicit manual direct or reciprocal exchange rate. Without a configured rate, the native currency remains visible; cross-currency budget filters exclude prices that cannot be converted. Rates are labelled manual with update times and do not claim live market accuracy.

The public calculator at `/pricing-calculator` uses settings for commission %, service fee %, a currency-tagged flat fee, and tax on those fees. Fees round to the selected currency's minor unit using half-up rounding. The result includes assumptions; it is a transaction-cost estimate, not an automated valuation or a tax/legal quote. Flat-fee currency conversion needs a configured rate. All rates default to zero until set by the operator. The public calculator can be disabled in settings. Invoice/receipt prefixes are stored for the planned document module; document generation is not implemented by this settings change.

## Email and queues

Set `MAIL_MAILER=zeptomail`, `ZEPTOMAIL_API_KEY`, a verified `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. Set `ZEPTOMAIL_API_URL` to the sending URL shown for your account's data center; the default is `https://api.zeptomail.com/v1.1/email`. The endpoint is configurable because Zoho account/region endpoints differ. No keys are stored in the UI.

ZeptoMail is used for **transactional** mail. Marketing campaigns need a separate provider and consent/unsubscribe workflows. SMS and WhatsApp adapters are not implemented yet.

Jobs use bounded retries and backoff. Inspect `php artisan queue:failed`, resolve the cause and replay a selected job with `php artisan queue:retry <uuid>`. Restart long-running workers after deployment with `php artisan queue:restart`. Failed follow-ups remain visible and can be retried through the failed job.

Delivery is **at least once**: an email provider may accept a request just before a worker crashes, so a retry can duplicate a message. Inbox writes and mail delivery are not a single atomic operation. Follow-up jobs use an overlap lock; payment state transitions use database locks. Production supervision, monitoring and an outbox/reconciliation policy should be added before higher-volume operation. If a dispatching process crashes between commit and queue publication, inspect pending webhook events or queued follow-ups and replay/requeue the corresponding job.

## Payments

`PAYMENTS_ENABLED=false` by default. This preserves the requirements' distinction: property sale/rent payments can be recorded manually, while optional gateway requests can collect platform fees. Manual records support the configured currency catalogue, including 0, 2, 3, 4 and custom decimal units. The original currency and minor-unit precision are stored on each payment; there are no payouts, refunds, escrow, subscriptions, invoice generation or commission settlement yet.

Before enabling gateway requests, configure sandbox credentials and a public HTTPS webhook URL in each provider dashboard:

| Provider | Credentials | Webhook path | Signature |
| --- | --- | --- | --- |
| Paystack | `PAYSTACK_SECRET_KEY` | `/webhooks/paystack` | HMAC SHA-512 raw body, `x-paystack-signature` |
| Flutterwave **v3** | `FLUTTERWAVE_SECRET_KEY`, `FLUTTERWAVE_WEBHOOK_SECRET` | `/webhooks/flutterwave` | Configured secret hash, `verif-hash` |
| Eversend Merchant v1 | `EVERSEND_CLIENT_ID`, `EVERSEND_CLIENT_SECRET`, `EVERSEND_WEBHOOK_SECRET` | `/webhooks/eversend` | HMAC SHA-512 raw body, `x-eversend-signature` |

Eversend uses `GET /auth/token`, `POST /collections/tempVirtualAccounts` and `GET /transactions/{transactionId}`. This account-specific product requires business approval. Automatic settlement requires the verified transaction's `transactionRef` to match the temporary account reference stored locally. Events with missing/unmatched references deliberately remain unconfirmed and require reconciliation. Validate this correlation with your Eversend sandbox/account before enabling it; never settle by amount or customer email alone.

The Flutterwave adapter targets the documented v3 secret-hash webhook format, not another API version's signature scheme.

Checkout POSTs are not automatically retried after network failures to avoid creating duplicate external requests. A repeated form submission uses the same UUID and cannot create a second local payment. An initialization timeout may still have created a provider-side transaction; investigate before making another request. The browser never marks a payment paid.

An hourly task re-verifies pending Paystack payments. For a missed webhook, operators can queue a server check:

```sh
php artisan payments:verify paystack <local-reference>
php artisan payments:verify flutterwave <provider-transaction-id>
php artisan payments:verify eversend <provider-transactionId>
php artisan payments:reconcile --reference=<local-reference>
```

A successful provider check must match the local reference, exact amount and currency. Failed, refunded and disputed events do not currently update payment accounting; add those workflows before production use. Webhook payloads may contain customer information; restrict database/log access and define retention before go-live.

Provider references: [ZeptoMail send API](https://www.zoho.com/zeptomail/help/api/email-sending.html), [Paystack webhooks](https://paystack.com/docs/payments/webhooks/), [Flutterwave v3 webhooks](https://developer.flutterwave.com/docs/webhooks), [Eversend authentication](https://eversend.readme.io/reference/authentication-1), [Eversend webhooks](https://eversend.readme.io/reference/webhooks), [Eversend temporary accounts](https://eversend.readme.io/reference/ngn-collection-copy).

## Verification

```sh
php artisan test
php vendor/bin/pint --format agent
```

On this machine, use the explicit PHP 8.4 binary with `-n` for both commands (or call `-n vendor/bin/phpunit` directly). Tests fake provider HTTP calls and do not send mail or move money. Tests cover panel access, agency isolation, user escalation attempts, disabled accounts, auth recovery, notification ownership, follow-up scheduling, webhook signatures/deduplication, payment verification and provider payloads.

MySQL compatibility can be checked against a **separate empty testing database**:

```sh
DB_CONNECTION=mysql DB_DATABASE=real_estate_ecosystem_testing php vendor/bin/phpunit
```

Never point test runs at the development or production database: the test suite migrates tables. The separate `real_estate_ecosystem_testing` database was used for local MySQL verification.

## Next implementation phases

1. Property sales/rentals and CRM: property/media models, scoped clients, lead stages, preferences, inquiries and assignments.
2. Follow-up workflows: appointments/viewings, communication history, templates, campaign consent, SMS/WhatsApp connectors and delivery receipts.
3. Flagship lead discovery, valuation and AI: permitted-source connectors, provenance and deduplication, comparable-property data, explainable estimates and provider-backed assistance.
4. Agent commissions and referrals; leases, maintenance, inspections, receipts/invoices and documents.
5. Finance, branches/payroll, professional services and construction/project inventories.

The roadmap UI explicitly labels unfinished workflows. No scraper, valuation model, AI provider, campaign sender or referral network is represented as functioning yet.

For production, configure HTTPS and secure cookies, `APP_ENV=production`, `APP_DEBUG=false`, a dedicated database account, worker supervision, a minute-level scheduler, provider sandbox validation, backups, log/payload retention and application monitoring. Deploy behind a PHP 8.4 web runtime rooted at `public/`, then run Laravel's configuration/route/view cache commands.
