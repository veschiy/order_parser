# Implementation Plan — President of Ukraine Awards Parser

Status: planned, not implemented yet.

This document stores the agreed implementation plan. App code, migrations, and services
should follow this plan only after the quality gates below are in place.

## Goal

Build a Laravel 13 web app that:

- monitors a configurable RSS feed (default:
  `https://www.president.gov.ua/rss/documents/all.rss`)
- keeps only items whose description is
  `Про відзначення державними нагородами України`
- parses linked decree pages with a browser-like User-Agent
- extracts order number, awarded people, ranks, and awards
- stores person names in nominative case
- lets the user search stored people to see if they were awarded
- allows specifying either an RSS URL or a single decree URL manually

NativePHP packaging is a later concern; the app must remain a normal Laravel app.

---

## Quality gates (must pass before/after implementation)

Run all gates with:

```sh
composer check
```

Current gates:

- `composer lint` — Laravel Pint style check (`vendor/bin/pint --test`)
- `composer test` — PHPUnit suite (`php artisan test`)

Implementation must add tests for every new behavior and keep all gates green.

---

## Phase 1 — UI shell (first priority)

Pages/layouts/routes described in the planning conversation. Deliverables:

- `resources/views/layouts/app.blade.php`
- `resources/views/search/index.blade.php`
- `resources/views/orders/index.blade.php`
- `resources/views/orders/show.blade.php`
- `resources/views/parse/index.blade.php`
- Controllers and form request for parsing/search

---

## Phase 2 — Database design

Tables:

- `orders`
- `awardees`
- optional `parse_runs`

Models:

- `Order`
- `Awardee`
- optional `ParseRun`

---

## Phase 3 — Services and parsing

Services:

- `App\Services\Rss\FeedParser`
- `App\Services\Documents\DecreeParser`
- `App\Services\Parsers\AwardsParserService`
- `App\Services\Http\BrowserlikeClient` or equivalent HTTP wrapper

Rules:

- browser-like User-Agent, Accept, Accept-Language headers
- timeouts and retries
- partial-failure reporting for batch RSS parsing

---

## Phase 4 — Ukrainian name normalization

- Convert awarded names to nominative case
- Prefer a PHP-compatible Ukrainian declension library if available
- fallback heuristic allowed, with raw and normalized names stored

---

## Phase 5 — Search

- case-aware search by full name/surname/given name
- pagination
- clear no-result state

---

## Phase 6 — Validation, errors, resilience

- URL validation
- HTTP/XML/parse error handling
- Ukrainian user-facing messages
- technical error logging

---

## Phase 7 — Tests

Unit:

- RSS item filtering
- decree number extraction
- name normalization
- duplicate prevention

Feature:

- search page
- single URL parse
- RSS parse
- order and awardee listings/detail

Use `Http::fake()` and no real network calls in tests.

---

## Phase 8 — NativePHP readiness later

- Re-add `nativephp/desktop` only when packaging is needed
- recreate NativePHP config/provider/scripts
- no app-logic changes expected
