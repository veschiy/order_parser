# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

This is **Order Parser 1.0.0**, a Laravel 13 web app (PHP `^8.3`; currently running 8.5) for parsing Ukrainian presidential award decrees. It monitors a configurable RSS feed (default `https://www.president.gov.ua/rss/documents/all.rss`), keeps only items whose description is `Про відзначення державними нагородами України`, fetches linked decree pages with browser-like headers, extracts order number/title/date and awarded people (rank, award), normalizes person names to the nominative case, stores results in SQLite, and provides search, order listings/detail, CSV export, soft-delete/restore, and awardee-name editing through a Blade UI.

Implementation details and design rationale live in `PLAN.md`; Windows/desktop packaging notes in `BUILD_NATIVE_WINDOWS.md`. The project is still structured as a normal Laravel web app. NativePHP is a declared dependency and its scaffold/config exists, but desktop builds are a separate concern (see that doc).

## Commands

Initial setup (installs PHP/JS deps, creates `.env`, generates app key, runs migrations, builds assets):

```sh
composer setup
```

Manual equivalent: `composer install`, copy `.env.example` to `.env` if missing, `php artisan key:generate`, `php artisan migrate --force`, `npm install --ignore-scripts`, `npm run build`.

Development:

- Web app (server, queue, logs, and Vite together): `composer dev`
- Frontend dev server: `npm run dev`; production build: `npm run build`

Database (SQLite by default, `database/database.sqlite`):

- `php artisan migrate`, `php artisan migrate:fresh`, `php artisan db:wipe`, `php artisan db:seed`

Testing and quality gates (dev deps must be installed first):

- All configured gates: `composer check` (PHPStan, Rector dry-run, Pint, Pest)
- Individual gates: `composer phpstan`, `composer rector`, `composer pint`, `composer pint:fix`, `composer pest`
- PHPUnit suite (clears cached config first): `composer test` or `php artisan test`
- Single test file: `php artisan test tests/Feature/ParseDecreeTest.php`
- Single test: `php artisan test --filter=TestName` (or `vendor/bin/phpunit --filter TestName` once installed)
- Tests use in-memory SQLite, array cache/session, sync queue (`phpunit.xml`). HTTP calls are faked with `Http::fake()`; no real network calls in tests.

Linting/formatting (Laravel Pint, Laravel preset in `pint.json`):

- Apply fixes: `vendor/bin/pint` (or `composer pint:fix`)
- Check without changes: `vendor/bin/pint --test` (or `composer pint`)

Static analysis/rector:

- PHPStan at level max over `app` and `tests`: `composer phpstan`
- Rector (PHP 8.4 set + Laravel preset, dry-run): `composer rector`

## Architecture

This is a Laravel 13 application with an app-specific domain layered under `app/`.

Routing (`routes/web.php`):

- `/` — search page (`SearchController`)
- `/orders`, `/orders/{id}`, `/orders/{id}/export.csv` — order listing/detail/CSV export
- `/orders/{id}` (DELETE) and `/orders/{id}/restore` (PATCH) — soft delete / restore
- `/awardees/{awardee}` (PATCH) — inline awardee name edit (JSON)
- `/parse`, `/parse` (POST), `/parse/database` (DELETE) — parse UI/store/clear DB

Controllers (`app/Http/Controllers/`):

- `SearchController` — name search over active orders only
- `OrderController` — listing, detail, CSV export, soft delete/restore
- `AwardeeController` — validates and updates `full_name`
- `ParseController` — dispatches RSS or single-document parse, formats summary status

Services (`app/Services/`):

- `Rss\FeedParser` — fetches RSS, keeps items whose description exactly matches the target string
- `Http\BrowserlikeClient` — browser-like headers; uses Laravel HTTP for foreign URLs, cURL with retry/backoff for `president.gov.ua` (handles its anti-bot checkpoint)
- `Documents\DecreeParser` — fetches decree HTML, converts to text, extracts number/title/published date and awardees
- `Parsers\AwardsParserService` — orchestrates RSS/single parse, transactions, duplicate handling, and `ParseRun` tracking
- `Names\UkrainianNameNormalizer` — converts awarded names to nominative case (uses `Shevchenko` library plus heuristics)

Models (`app/Models/`):

- `Order` — soft-deleted; unique by `document_url`; casts `published_at`/`parsed_at`; `displayNumber()`
- `Awardee` — belongs to order; unique on `order_id + full_name + award`
- `ParseRun` — records mode, source, status, counts, errors, timing

Persistence (`database/migrations/`): `orders` (incl. `raw_text`, `raw_html`, soft deletes), `awardees`, `parse_runs`, plus standard sessions/cache/jobs migrations. Default DB is SQLite.

Frontend: Vite 8 + Tailwind CSS 4; Blade views in `resources/views` (`layouts/app`, `search/index`, `orders/index`, `orders/show`, `parse/index`).

Tests:

- `tests/Feature/*` — HTTP/parse/search behavior using `Http::fake()`
- `tests/Unit/UkrainianNameNormalizerTest.php`
- Fixtures: `tests/Fixtures/rss.xml`, `tests/Fixtures/decree_107.html`

Key invariants to preserve when changing code:

- RSS item filtering is by exact description string.
- Decree fetching must keep browser-like User-Agent/Accept/Accept-Language (and retries on president.gov.ua).
- Names are normalized to nominative before storage; raw surname/given/patronymic are stored as parsed.
- Re-parsing the same document upserts the order by `document_url` and dedups awardees by `order_id + full_name + award`; `ParseRun` rows still record each attempt.
- Orders are soft-deleted; search and listing behavior depends on active-state filtering.
