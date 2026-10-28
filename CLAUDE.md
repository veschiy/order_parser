# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

This is a **parser app**. It will fetch a web page (target site to be specified later) and
extract structured information from it, persisting the results in the database. The data
should be searchable/browsable through a friendly web UI. The application is built as a
Laravel web app; desktop packaging via NativePHP is **not** available on the current dev
machine and `nativephp/desktop` has been removed for now (re-add later when needed).

## Commands

Initial setup (installs PHP/JS deps, creates `.env`, generates app key, runs migrations, builds assets):

```sh
composer setup
```

Equivalent manual steps: `composer install`, copy `.env.example` to `.env` if missing, `php artisan key:generate`, `php artisan migrate`, `npm install --ignore-scripts`, `npm run build`.

Development:
- Web app (server, queue, logs, and Vite together): `composer dev` (runs `php artisan dev`).
- Frontend dev server: `npm run dev`; production build: `npm run build`.

Database (SQLite by default):
- `php artisan migrate`, `php artisan migrate:fresh`, `php artisan db:wipe`, `php artisan db:seed`.

Building (none for desktop at present — `nativephp/desktop` is not installed).

Testing (PHPUnit 12):
- All tests: `composer test` (clears cached config first) or `php artisan test`.
- Single suite/file: `php artisan test tests/Feature/ExampleTest.php`.
- Single test: `php artisan test --filter=TestName` (or `vendor/bin/phpunit --filter TestName`).
- Tests use in-memory SQLite, array cache/session, sync queue (see `phpunit.xml`).

Linting/formatting (Laravel Pint):
- Apply fixes: `vendor/bin/pint`.
- Check without changes: `vendor/bin/pint --test`.

## Architecture

This is a Laravel 13 application (PHP `^8.3`).

- `app/Providers/AppServiceProvider.php` is the standard (currently mostly empty) Laravel provider.
- Routing: `routes/web.php` defines `/` returning the `welcome` view; `routes/console.php` defines the `inspire` command. Domain models/controllers for the parser are not yet built.
- Frontend: Vite 8 + Tailwind CSS 4. Entries `resources/js/app.js` and `resources/css/app.css`; Blade views live in `resources/views`. Production rendering depends on `public/build/manifest.json` (built by `npm run build`).
- Database: SQLite by default (`DB_CONNECTION=sqlite`); migrations in `database/migrations`, factory in `database/factories/UserFactory.php`, seeder in `database/seeders/DatabaseSeeder.php`.
- Testing: PHPUnit 12 with `tests/Unit` and `tests/Feature` suites, base `tests/TestCase.php`; env overrides in `phpunit.xml` (in-memory SQLite, array stores, sync queue).
- Code style: `.editorconfig` uses 4-space indentation (2 for YAML) and LF line endings. Laravel Pint is the formatter; no custom `pint.json` exists.

Notes:
- Target website for parsing is not yet specified; add parser services/HTTP client calls under `app/` (e.g. an `app/Parsers/` or `app/Services/` namespace) and models for stored results under `app/Models/`.
- NativePHP desktop config, provider, and binary runtime have been removed; restore `nativephp/desktop` and reintroduce `config/nativephp.php` + a `NativeAppServiceProvider` when desktop building is needed again.
