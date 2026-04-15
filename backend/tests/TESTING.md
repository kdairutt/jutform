# JutForm automated tests

This suite documents and tests the current API behavior for the JutForm backend.

## What we test

- **Authentication**: login, logout, profile, session-backed access control.
- **Routing**: public vs authenticated routes per [`config/routes.php`](../config/routes.php).
- **Core flows**: forms CRUD (against seeded data where async `form_setup` is involved), submissions (public create, owner list/export), reference data, search with representative title-based requests, scheduled email creation, webhooks creation, file upload/download, admin authorization for revenue/logs.

Tests run against a **seeded MySQL database** (see project `README` and `seeds/seed.php`). On a fresh environment the initial dataset is loaded by `docker compose up`; use `./scripts/reset-db.sh` to restore that baseline before `phpunit` if needed.
Each test runs inside a database transaction that is rolled back in `tearDown()` so seeded state stays stable across repeated runs.

## Test guidelines

- Keep endpoint coverage focused on routes and flows that are implemented in the current application.
- Add or update targeted tests where they best fit when behavior is corrected and should stay stable over time.

## Environment

| Variable   | Typical local (host) | Docker `php-fpm` |
|-----------|-----------------------|------------------|
| `DB_HOST` | `127.0.0.1`           | `mysql`          |
| `DB_PORT` | `3307`                | `3306`           |
| `DB_NAME` | `jutform`             | `jutform`        |
| `DB_USER` | `jutform`             | `jutform`        |
| `DB_PASS` | `jutform_secret`      | `jutform_secret` |

PHPUnit sets `JUTFORM_TESTING` to enable captured responses (no real `exit()`).

See [`bootstrap.php`](bootstrap.php) in this directory and [`run-tests.sh`](../../scripts/run-tests.sh).
