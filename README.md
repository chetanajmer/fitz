# Fitnix

Multi-tenant gym management SaaS for Indian gyms. One install serves many gyms; every gym sees only its own data. Gyms pay the platform per active member per month (default ₹20).

| Area | URL | Who |
| --- | --- | --- |
| Platform panel | `/platform` | Super Admin: gyms, pricing, SaaS invoices, leads, shared library |
| Gym panel | `/app` | Gym admin and trainers: members, enquiries, plans, payments, attendance, diet/workout plans |
| Member portal | `/me` | Members with a portal login |
| Member app API | `/api/v1` | Mobile app: OTP login, plans, streaks, check-in (Postman collection in `postman/`) |
| Public pages | `/{gym-slug}`, `/join/{token}`, `/register/{token}` | Gym profile, enquiry form, desk self-registration |

**Stack:** PHP 8.3, Laravel 13, Livewire 4, Tailwind CSS 4 + Vite, MySQL 8.4, spatie/laravel-permission, Sanctum.

## Requirements

- PHP 8.3 with the `zip`, `intl`, `gd`, `mbstring`, `fileinfo`, `pdo_mysql` and `pdo_sqlite` extensions. On Laragon, enable `zip` under Menu → PHP → Extensions, or `composer install` fails.
- Composer 2
- MySQL 8.4
- Node 22

## Local setup

```bash
git clone https://github.com/chetanajmer/fitz.git
cd fitz
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database called `gymai` (the defaults in `.env.example` expect user `root` with no password), then pick one way to fill it.

**Option A: demo data from seeders**

```bash
php artisan migrate
php artisan db:seed                      # roles, platform settings, Super Admin
php artisan db:seed --class=DemoSeeder   # demo gym "Iron Temple Fitness"
```

Set `SUPER_ADMIN_PASSWORD` in `.env` before `db:seed`, or the Super Admin is skipped.

**Option B: a shared database dump**

```bash
mysql -u root gymai < path/to/gymai.sql
php artisan migrate                      # applies any migrations newer than the dump
```

Never commit a dump. It contains password hashes, sessions and tokens, and `*.sql` is git-ignored.

Then, for either option:

```bash
php artisan storage:link
npm install
npm run build
php artisan serve                        # http://127.0.0.1:8000
```

During frontend work, run `npm run dev` next to `php artisan serve`. `composer run dev` starts the dev processes in one terminal.

### Demo logins (DemoSeeder)

| Role | Email | Password |
| --- | --- | --- |
| Gym admin | `owner@demo.gymai.test` | `password` |
| Trainer | `trainer@demo.gymai.test` | `password` |
| Member portal | `member@demo.gymai.test` | `password` |

Member app OTPs are written to `storage/logs/laravel.log` while `SMS_DRIVER=log`.

## Scheduled jobs

Production needs a cron entry running `php artisan schedule:run` every minute. Locally, run `php artisan schedule:work`.

| Command | When | What |
| --- | --- | --- |
| `gymai:refresh-memberships` | Daily 00:10 | Moves memberships between upcoming, active and expired by date |
| `gymai:refresh-streaks` | Daily 00:20 | Recalculates attendance streaks |
| `gymai:generate-invoices` | 1st of month, 02:00 | Creates each gym's SaaS invoice for the previous month |

## Tests and code style

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

Feature tests run on in-memory SQLite and need built assets (`npm run build`). CI (`.github/workflows/tests.yml`) runs Pint and the full suite on every push and on pull requests to `main`.

## Working on the code

- **Branches:** each developer works on their own branch (`dev-ekansh`, `dev-naresh`). Nothing is committed to `main` directly; changes reach `main` through a pull request after CI passes.
- **Tenancy:** gym-owned models use the `BelongsToGym` trait, which scopes every query to the current gym. Never take `gym_id` from request input.
- **Business logic** lives in `app/Services`; Livewire components and controllers stay thin.
- **Migrations:** never edit one that has already run; add a new one. Run `php artisan migrate` after every pull.
