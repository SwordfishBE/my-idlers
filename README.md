# My idlers

A self-hosted web app for displaying, organizing and storing information about your servers (VPS/Dedi), shared &
reseller hosting, seedboxes,
domains, DNS and misc services.

Despite what the name infers this self-hosted web app isn't just for storing idling server information. By using
a [YABS](https://github.com/masonr/yet-another-bench-script) output you can get disk & network speed values along with
GeekBench 5 & 6 scores to do easier comparing and sorting. Of course storing other services e.g. web hosting is possible
and supported too with My idlers.

[![Generic badge](https://img.shields.io/badge/version-4.2.0-blue.svg)](https://shields.io/) [![Generic badge](https://img.shields.io/badge/Laravel-13-red.svg)](https://shields.io/) [![Generic badge](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://shields.io/) [![Generic badge](https://img.shields.io/badge/Bootstrap-5.3-pink.svg)](https://shields.io/)

<img src="https://raw.githubusercontent.com/cp6/my-idlers/main/public/My%20Idlers%20logo.jpg" width="128" height="128" />

[View demo site](https://demo.myidlers.com/)

**Note:** Create, Update and Delete are disabled on the demo site.

## Project sponsor

Proudly sponsored by [PikaPods](https://www.pikapods.com/) - Deploy your own My Idlers instance with one click, no server setup required. PikaPods handles all the infrastructure so you can focus on managing your services.

[![PikaPods](https://www.pikapods.com/static/run-button.svg)](https://www.pikapods.com/pods?run=my-idlers)

## 4.2.0 changes

* Added optional expiry email reminders for active servers, domains, shared and reseller hosting, seedboxes and miscellaneous services, 14, 3 and 1 day before their due date.
* Added a recipient, time-zone and test-email control in Settings. Reminders are disabled by default.
* Added a backup-first upgrade command and reviewed migration checks. See [Updating](#updating).
* Updated application and frontend dependencies.

## 4.1.0 changes (February 2026):

* Added data export feature for all service types (servers, domains, shared, reseller, seedboxes, DNS, misc)
* Export data in JSON or CSV format
* Added export buttons to all index pages
* Added global export section to settings page for exporting all data at once
* Global CSV export creates a ZIP file with separate CSV files per service type
* Added new API endpoints for data export:
  - `GET /api/export/servers?format=json|csv`
  - `GET /api/export/domains?format=json|csv`
  - `GET /api/export/shared?format=json|csv`
  - `GET /api/export/reseller?format=json|csv`
  - `GET /api/export/seedboxes?format=json|csv`
  - `GET /api/export/dns?format=json|csv`
  - `GET /api/export/misc?format=json|csv`
  - `GET /api/export/all?format=json|csv`
* Added ExportService for centralized export logic
* Added comprehensive test suite for export functionality
* Modernized confirm-password and verify-email auth pages
* Redesigned error pages (401, 403, 404, 500, 503) with card layout and light/dark mode support

## 4.0.0 changes (February 2026):

* Updated Laravel version to 11.48
* Updated Bootstrap version to 5.3.8
* Updated all composer package versions
* Updated all npm package versions
* Fixed security vulnerabilities in dependencies
* Added comprehensive seeders for Users, Servers, Shared, Reseller, Domains, Misc and DNS
* Added YABS seeder with sample benchmark data
* Updated Dockerfile to PHP 8.4 with additional extensions (bcmath, pcntl)
* Improved Dockerfile with production optimizations and health check
* Improved run.sh with better env defaults, caching and optional auto-migrate
* Modernized auth pages (login, register, forgot/reset password) with clean design
* Added light and dark mode support for auth pages
* Added `MAX_USERS` env variable to control registration limit (0 = unlimited)
* Redesigned dashboard with cleaner stat cards, costs and resources sections
* Modernized navbar with improved styling and hover states
* Updated dark mode with new color scheme and page background
* Modernized all index pages with consistent card-based layout and improved table styling
* Improved DataTables styling (search, pagination, dropdown) for light and dark modes
* Fixed DataTables error alerts on empty tables
* Made demo user and sample data seeding optional via `SEED_DEMO_DATA` env variable
* Redesigned all create and edit pages with organized card sections and consistent form styling
* Redesigned all show/detail pages with polished detail cards and visual hierarchy
* Modernized settings page with organized card-based sections
* Modernized account page with profile and API token sections
* Modernized server and YABS comparison pages with improved table styling
* Modernized public server listing page with consistent design
* Added comprehensive test suite with 150 tests covering all major features
* Added servers index card view option in settings (table or cards layout)
* Fixed Vue.js loading issues on comparison pages
* Fixed server comparison null value handling
* Fixed inactive services still being counted in cost calculations and "Due Soon" ([#125](https://github.com/cp6/my-idlers/issues/125), [#112](https://github.com/cp6/my-idlers/issues/112))
* Fixed OS icons showing wrong icons for certain OS IDs ([#123](https://github.com/cp6/my-idlers/pull/123))
* Removed unused legacy auth pages and components

### Test Suite

The application now includes a comprehensive test suite with 150 tests and 294 assertions:

**Feature Tests:**
- Authentication (login, registration, password reset, email verification)
- Servers CRUD operations and validation
- Domains CRUD operations and validation
- DNS records CRUD operations and validation
- Providers, Locations, Labels, OS CRUD operations
- IP addresses CRUD operations with HTTP mocking
- Home dashboard functionality

**Unit Tests:**
- Server model (server types, comparison logic)
- Pricing model (cost calculations, term conversions)
- Settings model (sorting/ordering logic)
- Labels model (assignment operations)
- IPs model (IPv4/IPv6 handling)
- DNS model (type constants)

Run tests with:
```shell
vendor/bin/phpunit
# or with testdox output
vendor/bin/phpunit --testdox
```

### Database Seeding

The seeder is split into two parts:

**Core data (always seeded):** Settings, Providers, Locations, OS, Labels - required for the app to function.

**Demo data (optional):** Admin user and sample services (servers, domains, shared hosting, etc.)

```shell
# New, empty database only (core data only)
php artisan migrate --seed

# New, empty database only: to include demo data, set SEED_DEMO_DATA=true first.
php artisan migrate --seed
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SEED_DEMO_DATA` | `false` | Set to `true` to seed demo user and sample data during initial database seeding |
| `MAX_USERS` | `1` | Maximum number of users allowed to register. `0` = unlimited |

### Demo Login Credentials

When `SEED_DEMO_DATA=true` is set, a demo user is created:

| Email | Password |
|-------|----------|
| admin@admin.com | password |

#### Updating an existing installation

See [Updating](#updating). Never use `migrate:fresh`, `migrate:reset`, or `migrate:rollback` on an existing database.

## Requires

* PHP 8.3 or newer
* Node.js 22.22.2+, 24.15.0+, or 26+ to build and test frontend assets

## Features

* Add and manage servers, shared hosting, reseller hosting, seedboxes, domains, DNS and misc services
* [Auto get IP's from hostname](https://cdn.write.corbpie.com/wp-content/uploads/2021/01/my-idlers-self-hosted-server-domain-information-ips-from-hostname.gif)
* [Check up/down status](https://cdn.write.corbpie.com/wp-content/uploads/2021/01/my-idlers-self-hosted-server-domain-information-ping-up-feature.gif)
* Get YABS data from output or POST directly from yabs.sh
* Compare servers and YABS benchmarks
* Save & view YABS output with disk and network speeds
* Export data in JSON or CSV format
* Light and dark mode themes
* Next due date tracking with dashboard overview
* Multi currency and payment term support
* Pre-defined operating systems with icons
* Assign labels to services
* Assign server type (KVM, OVZ, LXC & dedi)
* Assign notes to any service
* Public server listing option
* REST API for all resources
* Registration limit control (MAX_USERS)

## Install

* Run `git clone https://github.com/cp6/my-idlers.git` into your directory of choice
* Run `composer install`
* Run `npm ci`
* Run `cp .env.example .env`
* Edit MySQL details and other settings in .env
* Run `php artisan key:generate`
* Run `php artisan make:database my_idlers` to create database
* Run `npm run prod` to build assets
* On a new, empty database only, run `php artisan migrate --seed`
* Run `php artisan serve`

## Updating

The upgrade command is for existing local MySQL or MariaDB installations. It does not install operating-system packages, deploy source code, modify `.env`, restore databases, or turn the site back on. Test the entire upgrade and restore process on a separate copy first. MySQL DDL can commit partially, so a migration failure requires inspection and may require restoring a backup; no command can guarantee zero data loss.

Before deploying updated code, make and test an independent backup of the existing database, `.env`, and any uploaded files. Keep copies outside the web root, encrypted as appropriate, and retain the existing `APP_KEY`, database credentials, HTTPS URL, Nginx configuration and application server settings. Never run `migrate:fresh`, `migrate:reset`, `migrate:rollback`, or seeders on production as an upgrade step.

1. Check PHP is 8.3 or newer, required extensions are installed, and a compatible `mariadb-dump` (for MariaDB) or `mysqldump` (for MySQL) is available. Run `composer check-platform-reqs --no-dev` after installing the release's locked dependencies.
2. Put the existing application in maintenance mode using `php artisan down`. Stop queue workers, scheduled jobs and all other writers, including external services, and prevent concurrent schema changes. Check maintenance mode is active before proceeding.
3. Deploy the reviewed release and run `composer install --no-dev --no-interaction`. Existing built assets are included; if rebuilding, use `npm ci` and `npm run production` on a machine meeting the documented Node.js requirement. Clear stale Laravel configuration using `php artisan config:clear`; do not replace `.env` or regenerate `APP_KEY`. Run `php artisan myidlers:check-config` to check prerequisites without printing or modifying secrets. It does not verify cron or actual email delivery.
4. Create a dedicated backup directory outside both the application directory and web root, owned and writable only by the account running the command (for example `mkdir -m 700 /path/to/private-backups`). Then run:

   ```sh
   php artisan myidlers:upgrade --backup-dir=/path/to/private-backups
   ```

   Confirm that all other database writers are stopped. The command checks for an existing migration history, local connection and InnoDB tables, takes a private SQL dump, and checks pending migrations against the reviewed migration list. It pauses before migrating: restore **that dump** to a separate empty database, verify its contents and migration history, and confirm only if the restore succeeds. A dump completion marker is not proof that every object or privilege was captured. The database account must have the necessary dump privileges, including for views, triggers, routines and events. Keep the application and all writers stopped during the backup, restore check and migration.
5. If migration or verification fails, leave maintenance mode enabled; investigate before recovery. MySQL schema changes may not roll back automatically. Restore only into an appropriately isolated database after checking the intended target, and verify data before reconnecting the application. After success, verify the site, database records and HTTPS; only then run `php artisan up` and resume the stopped writers.

The command deliberately rejects remote databases, non-InnoDB tables, unknown or modified pending migrations, split read/write connections, a missing migration history, cached configuration, and unsafe backup directories. A MySQL `DB_HOST=localhost` connection must have an explicit `DB_SOCKET` so the dump connects to the same server. A backup and restore strategy for other configurations must be validated separately; do not bypass these checks. Future migrations must be reviewed for data preservation and added to `config/reviewed-upgrade-migrations.php` using the SHA-256 of the migration file with CRLF line endings normalized to LF. Review includes testing with representative existing data, not merely updating a hash.

### Debian prerequisites

Check the installed Debian release, PHP version and extensions before changing packages: `cat /etc/os-release`, `php -v`, `php -m`, and `command -v mariadb-dump` or `command -v mysqldump`. On Debian 13, potential missing packages include `php8.4-cli`, `php8.4-mysql`, `php8.4-mbstring`, `php8.4-xml`, `php8.4-curl`, `php8.4-zip`, `composer`, and, for a MariaDB server, `mariadb-client`. Install only confirmed missing packages and use the same PHP version for CLI and the application server. Debian 12's standard PHP 8.2 packages do not satisfy this release; do not add an unverified repository or upgrade the operating system without a separate plan. For a MySQL server, use a compatible MySQL client instead of assuming the MariaDB client is interchangeable. Do not install a database server merely to obtain a dump client.

## Expiry email reminders

The app can send one email per active service 14, 3 and 1 calendar day before the saved next due date. This is the date recorded in My Idlers, not an independently verified registrar expiry date. Supported types are servers, domains, shared hosting, reseller hosting, seedboxes and miscellaneous services. DNS records do not have a due date. Reminders are disabled until enabled in Settings, where one recipient and an IANA time zone can be saved. The default time zone is `Europe/Brussels`; the app starts sending at 09:00 in the selected time zone and retries failed deliveries throughout the same day.

A server administrator must configure an SMTP provider in the existing private `.env` file, without replacing the file or changing `APP_KEY`. Supply the provider's actual values, including `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME`. `MAIL_SCHEME=smtp` is used for STARTTLS (normally port 587); use `MAIL_SCHEME=smtps` for implicit TLS (normally port 465). Existing `MAIL_ENCRYPTION=tls` and `MAIL_ENCRYPTION=ssl` are honored when `MAIL_SCHEME` is absent; an explicit `MAIL_SCHEME` takes precedence. An existing `MAIL_URL` alone is not sufficient for reminder mail: configure the individual `MAIL_HOST`, port, credentials and sender values. The dedicated reminder mailer requires TLS and validates the server certificate. Never commit credentials or post them in chat. After changing `.env`, clear Laravel's config cache with `php artisan config:clear`, or rebuild it according to the existing deployment procedure.

In Settings, save a valid recipient, then use **Send Test Email**. This sends a real test message to the saved address even while reminders are disabled; receiving it confirms the configuration works. The equivalent administrator command is `php artisan reminders:send --test`. The test does not send actual expiry reminders. Once the test arrives, enable reminders in Settings.

Laravel's scheduler must run every minute under the same account and PHP version used by the application. For example, add the following using that account's `crontab -e`, substituting the real application and private writable log paths and PHP binary:

```cron
* * * * * cd /absolute/path/to/my-idlers && /usr/bin/php artisan schedule:run >> /absolute/path/to/private-logs/my-idlers-scheduler.log 2>&1
```

The scheduler runs the reminder job every 15 minutes. Do not replace an existing crontab or add a second scheduler entry. Verify the existing scheduler and service account before installation; this repository cannot install cron on your server. Protect and rotate the scheduler log according to your normal server practices.

A delivery record prevents normal repeated runs from sending the same reminder twice to the same recipient for the same service, due date and interval. A failed send is retried on the next run, and interrupted claims are retried after one hour. If a process stops after the SMTP server accepts a message but before the app records success, a retry can occasionally duplicate that message; SMTP acceptance also does not guarantee inbox delivery. If the scheduler is offline for an entire reminder day, that day's reminder is not sent later. Monitor the scheduler and mail logs and use the test message to check delivery.

## Run using Docker

```
docker run \
  -p 8000:8000 \
  -e APP_URL=https://... \
  -e DB_HOST=... \
  -e DB_DATABASE=... \
  -e DB_USERNAME=... \
  -e DB_PASSWORD=... \
  -e MAX_USERS=0 \
  -e SEED_DEMO_DATA=false \
  ghcr.io/cp6/my-idlers:latest
docker exec ... php artisan migrate --seed --force  # New, empty database only
```

## Managed Hosting

Run with a single click on [PikaPods.com](https://www.pikapods.com/)

[![PikaPods](https://www.pikapods.com/static/run-button.svg)](https://www.pikapods.com/pods?run=my-idlers)

## Adding a YABS benchmark

yabs.sh now has JSON formatted response and can POST the output directly from calling the script.

With My idlers you can use your API key and the server id to directly POST the benchmark result

`https://yourdomain.com/api/yabs/SERVERID/USERAPIKEYISHERE`

Example yabs.sh call to POST the result:

`curl -sL yabs.sh | bash -s -- -s "https://yourdomain.com/api/yabs/SERVERID/USERAPIKEYISHERE"`

## Credits

IP who is data provided by [ipwhois.io](https://ipwhois.io/documentation)

## API endpoints

For GET requests the header must have `Accept: application/json` and your API token (found at `/account`)

`Authorization : Bearer API_TOKEN_HERE`

All API requests must be appended with `api/` e.g `mydomain.com/api/servers/gYk8J0a7`

**GET requests:**

| Endpoint | Description |
|----------|-------------|
| `dns/` | Get all DNS records |
| `dns/{id}` | Get DNS record |
| `domains/` | Get all domains |
| `domains/{id}` | Get domain |
| `servers` | Get all servers |
| `servers/{id}` | Get server |
| `IPs/` | Get all IPs |
| `IPs/{id}` | Get IP |
| `labels/` | Get all labels |
| `labels/{id}` | Get label |
| `locations/` | Get all locations |
| `locations/{id}` | Get location |
| `misc/` | Get all misc services |
| `misc/{id}` | Get misc service |
| `networkSpeeds/` | Get all network speeds |
| `networkSpeeds/{id}` | Get network speed |
| `os/` | Get all operating systems |
| `os/{id}` | Get operating system |
| `pricing/` | Get all pricing |
| `pricing/{id}` | Get pricing |
| `providers/` | Get all providers |
| `providers/{id}` | Get provider |
| `reseller/` | Get all reseller hosting |
| `reseller/{id}` | Get reseller hosting |
| `seedbox/` | Get all seedboxes |
| `seedbox/{id}` | Get seedbox |
| `settings/` | Get settings |
| `shared/` | Get all shared hosting |
| `shared/{id}` | Get shared hosting |
| `yabs/` | Get all YABS |
| `yabs/{id}` | Get YABS |
| `note/{id}` | Get note |
| `online/{hostname}` | Check if host is up |
| `dns/{domainName}/{type}` | Get IP for domain |

**Export endpoints (v4.1):**

| Endpoint | Description |
|----------|-------------|
| `export/servers?format=json\|csv` | Export servers |
| `export/domains?format=json\|csv` | Export domains |
| `export/shared?format=json\|csv` | Export shared hosting |
| `export/reseller?format=json\|csv` | Export reseller hosting |
| `export/seedboxes?format=json\|csv` | Export seedboxes |
| `export/dns?format=json\|csv` | Export DNS records |
| `export/misc?format=json\|csv` | Export misc services |
| `export/all?format=json\|csv` | Export all data |

**POST requests**

Create a server

`/servers`

Body content template

```json
{
    "active": 1,
    "show_public": 0,
    "hostname": "test.domain.com",
    "ns1": "ns1",
    "ns2": "ns2",
    "server_type": 1,
    "os_id": 2,
    "provider_id": 10,
    "location_id": 15,
    "ssh_port": 22,
    "bandwidth": 2000,
    "ram": 2024,
    "ram_type": "MB",
    "ram_as_mb": 2024,
    "disk": 30,
    "disk_type": "GB",
    "disk_as_gb": 30,
    "cpu": 2,
    "has_yabs": 0,
    "was_promo": 1,
    "ip1": "127.0.0.1",
    "ip2": null,
    "owned_since": "2022-01-01",
    "currency": "USD",
    "price": 4.00,
    "payment_term": 1,
    "as_usd": 4.00,
    "usd_per_month": 4.00,
    "next_due_date": "2022-02-01"
}
```

**PUT requests**

Update a server

`/servers/ID`

Body content template

```json
{
    "active": 1,
    "show_public": 0,
    "hostname": "test.domain.com",
    "ns1": "ns1",
    "ns2": "ns2",
    "server_type": 1,
    "os_id": 2,
    "provider_id": 10,
    "location_id": 15,
    "ssh_port": 22,
    "bandwidth": 2000,
    "ram": 2024,
    "ram_type": "MB",
    "ram_as_mb": 2024,
    "disk": 30,
    "disk_type": "GB",
    "disk_as_gb": 30,
    "cpu": 2,
    "has_yabs": 0,
    "was_promo": 1,
    "owned_since": "2022-01-01"
}
```

Update pricing

`/pricing/ID`

Body content template

```json
{
    "price": 10.50,
    "currency": "USD",
    "term": 1
}
```

**DELETE requests**

Delete a server

`/servers/ID`

## Notes

**Public viewable listings**

If enabled the public viewable table for your server listings is at `/servers/public`
You can configure what you want viewable at ```/settings```

**Due date / due soon**

This is simply just a reminder. If the homepage is requested (viewed) when a service is over due date it will get reset
to plus the term from the old due date.

E.g if the term is a month then the due date gets updated to be 1 month from the old due date.

**Supporting YABS commands:**

```curl -sL yabs.sh | bash```

or

```curl -sL yabs.sh | bash -s -- -r```

Logo icons created by Freepik - Flaticon
