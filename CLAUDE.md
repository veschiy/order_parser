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
- Fully imported documents are not re-fetched or re-imported, preserving manual awardee edits. Incomplete documents upsert the order by `document_url` and dedup awardees by `order_id + full_name + award`; `ParseRun` rows still record each attempt.
- Orders are soft-deleted; search and listing behavior depends on active-state filtering.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
