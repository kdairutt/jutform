# JutForm

JutForm is a form builder application. This repository contains the backend API, database schema, and Docker environment.

## Quick Start

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (4 GB+ RAM allocated)
- Git

### Setup

```bash
# Clone your repo
git clone <your-repo-url>
cd jutform

# Start all services, load the initial dataset, and apply migrations
docker compose up -d
```

On a fresh start, `docker compose up -d` loads the initial dataset and applies all migrations automatically. If you want to restore that baseline later, run `./scripts/reset-db.sh`.

### Access

| Service | URL |
|---------|-----|
| JutForm App | http://localhost:8080 |
| Mailpit (email viewer) | http://localhost:8025 |
| PHP-FPM Status | http://localhost:8080/fpm-status |
| MySQL | localhost:3307 (user: `jutform`, pass: `jutform_secret`) |
| Redis | localhost:6380 |

### Useful Scripts

```bash
./scripts/mysql-cli.sh          # Open MySQL CLI
./scripts/redis-cli.sh          # Open Redis CLI
./scripts/restart-workers.sh    # Restart PHP-FPM and queue worker
./scripts/run-tests.sh          # Run the test suite
./scripts/reset-db.sh           # Restore the initial seeded database state
./scripts/migrate.sh            # Apply pending migration scripts
./scripts/composer.sh <args>    # Run Composer inside the php-fpm container
./scripts/submit-form.sh <id>   # POST N synthetic submissions to a form (see --help)
```

### PHP Autoloader

Composer dependencies are installed automatically the first time `php-fpm` starts (if `vendor/` is absent). If you add, remove, or move PHP classes, regenerate the autoloader:

```bash
./scripts/composer.sh dump-autoload
```

### Logs

View logs using the helper script:

```bash
./scripts/logs.sh app            # PHP application error log
./scripts/logs.sh fpm-slow       # PHP-FPM slow requests (> 3 seconds)
./scripts/logs.sh fpm-access     # PHP-FPM access log with timing
./scripts/logs.sh nginx          # Nginx access log with response times
./scripts/logs.sh nginx-error    # Nginx error log
./scripts/logs.sh mysql-slow     # MySQL slow query log
```

Add `-f` to follow a log in real time, e.g. `./scripts/logs.sh app -f`.

You can also view logs through the admin panel at http://localhost:8080/admin/logs.

### Database Migrations

If your changes require a schema modification (new column, index, table, etc.) or need to correct existing data in the database, write a migration script rather than editing the base schema directly.

> **Warning:** Do not edit `docker/mysql/init/00-schema.sql`. That file only runs once on a fresh database and will not be re-executed on an existing one — any changes there will be silently ignored until someone wipes the database.

Place `.php` files in the `backend/migrations/` directory using a numeric prefix so they run in a predictable order:

```
backend/migrations/
  0001_add_some_column.php
  0002_backfill_existing_rows.php
```

Each migration is a plain PHP script that uses the existing `Database` class to get a PDO connection. See `backend/migrations/` for an example.

Migrations run automatically every time `docker compose up -d` starts (via the `migrator` service), so no manual step is needed on fresh start or after a reset.

If you add a new migration script while the stack is already running, apply it with:

```bash
./scripts/migrate.sh
```

Already-applied migrations are tracked in the `schema_migrations` table and are always skipped, so it is safe to run repeatedly.

> **Note:** A code-only fix is sometimes not enough. If a bug caused data to be stored incorrectly, existing rows will remain wrong until a migration updates them. When in doubt, check whether already-stored data also needs to be corrected.

### Support Tickets

See the `tickets/` directory for the list of issues to investigate and fix.

### Investigation Reports (optional)

For each ticket you work on, you may optionally write a short investigation report describing how you debugged it, the root cause you found, and the fix you applied. A template lives at `reports/TEMPLATE.md` and instructions are in `reports/README.md`.

Reports are not required, but they are a useful place to record findings for tickets you run out of time to fully fix.

### Running Tests

```bash
./scripts/run-tests.sh
```

## Architecture

- **Nginx** — Serves the React frontend and proxies API requests to PHP-FPM
- **PHP-FPM** — Runs the backend API
- **Queue Worker** — Processes async jobs (email sending, form setup, etc.)
- **MySQL 8** — Primary database
- **Redis** — Queue and caching
- **Mailpit** — Captures outgoing emails for inspection

## Local environment vs. production

The Docker setup in this repo is a local development environment built to mirror production as faithfully as possible — same PHP version, same MySQL version, same service topology (worker replicas, FPM workers, Redis, SMTP, etc.). Treat it as an approximation of the production deployment, not a rewriteable sandbox.

Fixes must live in application code: `backend/` source, `backend/migrations/`, and the test suite. Infrastructure files are **not** part of the solution surface:

- `docker-compose.yml`
- anything under `docker/` (Dockerfiles, nginx config, php-fpm pool config, mysql init, etc.)
- the compiled frontend in `frontend/dist/`

These files describe the environment, not the application. Changing them does not change production, so any fix that depends on editing them will not be accepted. If a bug looks like it can only be fixed by touching infra, re-read the ticket and the relevant backend code — the fix lives there.
