# citiESTATE — Real Estate Business Ecosystem

PHP **8.4** / Laravel 13 / MySQL foundation for the ecosystem described in `real-eastate-business.txt`. The responsive Blade interface uses local CSS and needs no frontend build.

This repository contains the working platform foundation and a public marketplace preview. Use it to manage accounts, organisation access, notifications, reminders, payment records and platform configuration. The full property-business ecosystem is being built in phases; opening a planned module does not activate a completed business workflow.

## Start here

- [How the system works](#how-the-system-works)
- [Local installation](#local-setup)
- [First-time administrator walkthrough](#first-time-administrator-walkthrough)
- [Accounts and permissions](#accounts-workspaces-and-access)
- [Branding, currencies and pricing](#super-admin-system-settings)
- [Email and background jobs](#email-and-queues)
- [Payments and verification](#payments)
- [Project structure](#project-structure)
- [Troubleshooting](#troubleshooting)
- [Testing](#verification)
- [Roadmap](#next-implementation-phases)

## How the system works

### Public website and inquiries

Visitors can browse the homepage, filter concept properties, view details, compare properties and use the pricing calculator without signing in. Favourites and comparison selections live in that browser's local storage; they do not sync between devices or become account records.

Inquiry forms save the visitor's request and preferences to MySQL. A permitted platform administrator reads them in **Public inquiries**. A submitted viewing request, listing request, valuation request or alert signup is an inquiry for review; it does not automatically publish a property, reserve a viewing, generate a valuation or start an alert campaign.

### One account, several workspaces

An account identifies a person. A workspace determines which business context that person is using. For example, the same person can be a buyer in their personal workspace and an agent in an agency workspace. Switching workspaces changes their available permissions, navigation and accessible records.

Each membership connects an account to a workspace and its roles. The server checks the active account, verified email, membership, enabled module, requested action and record scope. Hiding a sidebar item is only a presentation effect; permission checks also apply to direct requests.

| Workspace | What it is for | Typical users |
| --- | --- | --- |
| Personal | Individual profile, preferences and permitted personal activity | Buyer, owner, tenant, investor, service provider |
| Organisation | A particular agency or business and its scoped operational records | Agency administrator, branch manager, agent, accountant, other staff |
| Platform | Shared platform administration, organisations, public inquiries and global configuration | Super administrator, operations/compliance, support, according to their grants |

Platform administration does not automatically grant access to private organisation records. Global **System settings** and organisation **Workspace settings** are separate screens with separate access rules.

### Background delivery

The web application saves requests and queues jobs. A queue worker delivers notifications and transactional email, and verifies eligible payment events. The scheduler finds due follow-ups every minute and queues Paystack reconciliation hourly. Both the worker and scheduler must be running for these background workflows to complete.

For a scheduled reminder, the flow is: **pending → queued → sent**, with **cancelled** or **failed** where applicable. The worker rechecks relevant access before sending. Only pending reminders can be cancelled from the interface. In-app notifications are persistent inbox entries; toast notifications are temporary feedback such as “Settings saved” or validation errors.

### Money and payment records

A manual payment entry records a transaction that happened elsewhere and receives status `recorded`. It does not contact a provider or transfer money. An enabled gateway request creates checkout instructions and normally moves through `initializing → pending → paid`; initialization failures remain visible for investigation. A signed webhook queues a fresh provider-side check, and only a matching reference, amount and currency can confirm the payment.

Every payment keeps its currency and decimal precision. Changing the platform's default currency changes new-entry defaults and preferred property display; it does not convert old payment records. Manual exchange rates are used only where a display conversion or calculator flat-fee conversion is explicitly needed.

## Implemented

- Public editorial homepage at `/`, with local photography/fonts, a reduced-motion-aware architectural video hero, responsive search/filter forms, concept property/detail pages, locally saved favourites and comparisons, journal pages, and real inquiry capture.
- Public requests are stored in `public_inquiries` and reviewed by platform administrators at `/public-inquiries`. Property listings are clearly labelled concepts. No fabricated professionals, testimonials, verifications, market statistics or live availability are presented. Automated valuations, alert delivery, marketplace publishing and directory/referral workflows remain planned.

- Login/logout, email verification, password reset, throttling, encrypted database sessions and password-hash session invalidation.
- Scoped multi-role memberships, enabled-module checks, active/disabled accounts and memberships, organisation isolation, account creation and access editing, and audit records. Platform administrators are created through the CLI; the web interface cannot grant that role or change its accounts.
- Contextual personal, organisation and platform workspaces, with grouped sidebars for the 19 documented role templates and controlled workspace switching.
- Personal notification inbox, scoped recipient selection, read state, queued email and in-app delivery. Dismissible toast feedback supports success, error, warning and information messages; success messages auto-dismiss with hover/focus pause.
- Scheduled follow-ups, UTC storage with the configured input/display timezone (Africa/Lagos by default), cancellation, retries and terminal failure state. Agents currently schedule personal reminders; agency administrators can schedule reminders for their agency users.
- ZeptoMail API transport, including queued verification and reset emails. The default `log` mailer keeps development local.
- Manual payment recording without moving money. Optional Paystack and Flutterwave v3 hosted checkout; Eversend Merchant API NGN temporary virtual accounts.
- Signed webhooks, persisted event deduplication, queued server verification, exact amount/currency/reference matching, row-locked payment transitions and unique provider transaction IDs. Browser redirects never confirm payment.
- A roadmap showing all 16 modules and the six flagship features. The business workflows are planned, not complete.

## Local setup

Requirements: PHP 8.4 with PDO MySQL, mbstring, OpenSSL, cURL, DOM/XML and standard Laravel extensions; Composer; MySQL 8 or compatible MariaDB. Install PDO SQLite for the default isolated test suite.

```sh
git clone https://github.com/DOVEPCDIABLE/land-And.-property-managemnt-system-.git citiestate
cd citiestate
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

The public and workspace interfaces load assets directly from `public/css`, `public/js` and `public/media`. Node.js and a Vite build are optional for the current screens. PHP dependencies are installed by Composer; `vendor/`, local `.env`, runtime data and uploaded branding files are excluded from Git. A fresh clone creates a fresh database through migrations and does not include accounts from another installation.

### First-time administrator walkthrough

1. Complete installation, run migrations/seeding and create your super administrator with `ecosystem:admin`. Sign in at `/login` using the email and password you chose. CLI-provisioned administrators are already email-verified; public registrations require the verification-email flow.
2. Select the **platform workspace** in `/workspaces`. Open **Administration → System settings** to set the platform name, logos, public contacts, colours, enabled currencies, preferred currency, timezone and calculator assumptions. Save the form; feedback appears as a toast. Reload before retrying if another administrator has changed its revision.
3. Add explicit exchange rates if property display or flat-fee conversion is needed. For example, a pair means “1 unit of the base currency equals the entered number of quote-currency units.” No live-rate service is configured.
4. Create an organisation in `/organisations`, selecting an existing account as its administrator, or provision an agency administrator using the CLI command above. The organisation receives its own workspace.
5. Switch into that organisation. Use **Workspace settings** to enable permitted modules, then **Users & Access** to add members and assign roles/scopes. Add branches and teams where required. An existing member keeps the same account across organisations.
6. With the required permission, send a test in-app notification or schedule a reminder for an eligible recipient. Check the inbox, worker output and local mail log before configuring external mail.
7. Record a manual test payment in the appropriate workspace. Configure provider sandbox credentials, webhook endpoints and the gateway processing switch before attempting gateway collection. Personal workspaces cannot initiate gateway collection requests.
8. Browse `/` and submit an inquiry with your own test details. Return to the platform workspace and confirm it appears in `/public-inquiries`.

### Main screens

Access depends on the selected workspace, roles and enabled modules. A URL existing does not give every account permission to open it.

| Screen | Path | Purpose |
| --- | --- | --- |
| Public marketplace | `/` and `/properties` | Browse and filter concept properties |
| Pricing calculator | `/pricing-calculator` | Estimate configured transaction costs, when enabled |
| Sign in / registration | `/login`, `/register` | Access an existing account or create a personal account |
| Dashboard | `/dashboard` | Overview for the active workspace |
| Workspace switcher | `/workspaces` | Select an accessible workspace |
| Global system settings | `/system/settings` | Super-admin branding, currencies, pricing and payment policies |
| Workspace settings | `/workspace/settings` | Workspace configuration and module access |
| Organisations | `/organisations` | Platform organisation administration |
| Users & Access | `/users` | Permitted account and membership administration |
| Branches and teams | `/workspace/branches` | Organisation structure |
| Notifications | `/notifications` | Read your inbox; permitted users can send notifications |
| Follow-ups | `/follow-ups` | Schedule and review scoped reminders |
| Payment records | `/payments` | Manual records and permitted gateway requests |
| Public inquiries | `/public-inquiries` | Review website requests |
| Audit | `/workspace/audit` | Review permitted administrative activity |
| Account | `/account/profile`, `/account/security`, `/account/preferences` | Profile, credentials, sessions and notification preferences |

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

For example, with a USD 1,000 price, 5% commission, 2% service fee, a USD 100 flat fee and 10% tax on fees, the calculator shows USD 50 commission + USD 20 service + USD 100 flat fee + USD 17 tax = **USD 187 fees**, or **USD 1,187 including the price**. These are illustrative administrator-defined inputs, not built-in statutory charges. Calculator submissions do not create invoices, payment requests or saved quotations.

Settings saved through the UI take precedence over their defaults in `config/platform.php`. Before a processing preference is saved there, gateway enablement falls back to `PAYMENTS_ENABLED` in `.env`; afterwards, use the super-admin switch to change it. API keys and sending-domain credentials stay in server configuration. The locale selection does not provide a fully translated interface; the current interface copy is English.

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

## Project structure

| Location | Responsibility |
| --- | --- |
| `routes/web.php` | Public, authentication, workspace, settings and webhook routes |
| `routes/console.php` | Recurring follow-up, reconciliation and failed-job cleanup schedules |
| `app/Http/Controllers` | Web actions and responses |
| `app/Http/Requests` and `app/Http/Middleware` | Validation, active-account checks, workspace context and authorization |
| `app/Services/WorkspaceAccess.php` and `Tenancy.php` | Module permissions, record scopes and recipient boundaries |
| `app/Services/PlatformSettings.php`, `CurrencyService.php`, `PricingCalculator.php` | Global defaults/overrides, exact money display/conversion and fee calculations |
| `app/Services/Payments` | Provider adapters, exact money parsing and verified results |
| `app/Jobs`, `app/Notifications`, `app/Mail` | Queued work, notification delivery and ZeptoMail transport |
| `app/Models` | Accounts, memberships, records, settings and persisted events |
| `database/migrations` and `database/seeders` | Database schema and role/permission setup |
| `config/workspaces.php` | Role templates, permissions, modules and scopes |
| `config/marketplace.php` | Concept property catalogue and editorial content |
| `config/currencies.php` and `config/platform.php` | Initial currency metadata and global setting defaults |
| `resources/views` | Blade public pages and workspace screens |
| `public/css`, `public/js`, `public/media` | Directly served styles, interactions and bundled media |
| `tests/Feature` | Access, messaging, marketplace, payment and settings regression tests |

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| PHP version error or blank page under XAMPP | Use PHP 8.4 for both web requests and CLI commands. Point the web server at `public/`. See the machine-specific commands above. |
| Database connection or missing-table error | Confirm `DB_*`, MySQL availability and socket/port, then run `php artisan migrate --seed`. |
| No administrator after installation | Seeding creates permissions, not a default login. Run `php artisan ecosystem:admin` with your email. |
| Verification or password-reset email does not arrive | Run the `notifications` queue worker. With `MAIL_MAILER=log`, read the local log; with ZeptoMail, check the verified sender, credentials and failed jobs. |
| Reminder remains pending | Ensure the scheduler runs every minute; `php artisan followups:dispatch` can queue due reminders manually. Then check the worker and the recipient's active membership/preferences. |
| A screen is missing or returns 403 | Check the selected workspace, active membership, role permissions, scope and enabled modules. Global settings require a super-admin in the platform workspace. |
| A screen says “Planned” | Its business workflow has not been implemented; enabling the module does not build it. |
| Prices stay in NGN after choosing another currency | Configure an explicit exchange-rate pair. Without one, original property currencies remain visible. |
| A gateway rejects a currency | Record-currency support is broader than gateway support. Check provider policy, integration limits and merchant eligibility. |
| Checkout returns but payment is still pending | Browser returns cannot confirm money. Check webhook delivery, the `payments` worker and server verification; use the verification commands above for a missed event. |
| Environment changes do not take effect | Run `php artisan config:clear`, then restart queue workers. A saved UI setting overrides its server default. |
| Settings save reports a revision conflict | Reload to review the latest values before saving your changes again. |

## Deployment and stored data

For a hosted installation, set `APP_URL` to its public HTTPS address and configure MySQL plus a writable `storage/` and `bootstrap/cache/`. Run the database queue worker under a process supervisor, and invoke `php artisan schedule:run` once per minute from the project directory. Keep the PHP executable consistent between the web server, worker and scheduler.

Deploy migrations with `php artisan migrate --force`, cache configuration/routes/views after setting the environment, and restart workers after changing code. Create the initial administrator through the CLI, then finish platform setup in the UI. Never run `migrate:fresh` against a database you want to retain.

Back up MySQL, uploaded branding under `public/media/branding/`, and any application files stored under `storage/app/`. Preserve the installation's `APP_KEY` securely with its backups; do not regenerate it on an existing installation. Git contains application source and example configuration, not the live database, login accounts, provider secrets or uploaded branding.

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
