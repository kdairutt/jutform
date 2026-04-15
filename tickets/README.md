# Support tickets and feature requests

This folder lists bug reports and feature prompts for JutForm.  
Use these together with the application README and your local environment.

- Numbered `TICKET-*` files describe issues users are seeing.
- Numbered `FEATURE-*` files describe new work to implement.

Investigate using logs, the database, Redis, and network tools as needed.

## Picking what to work on

There is intentionally more work here than fits in the time window. You are not expected to finish everything — decide what to pick up based on impact, difficulty, and your own strengths. Treat this like a real backlog: read a few tickets first, form a plan, then dig in. A few well-investigated fixes are worth more than many shallow attempts.

## Scope of a fix

Fixes must be in application code — `backend/` source, `backend/migrations/`, and tests. Infrastructure (`docker-compose.yml`, anything under `docker/`, the compiled `frontend/dist/`) mirrors production and is out of scope: editing it does not change production, so it will not be accepted as a fix. See the "Local environment vs. production" section in the top-level README for the full list.
