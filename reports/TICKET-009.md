# Investigation Report — TICKET: Site freezes during business hours

## Debugging Steps

1. Reviewed PHP-FPM logs during reported freeze windows. there weren't any no explicit errors, but worker pool was suspected.
2. Identified `/api/forms/analytics` endpoint as a candidate since it calls an external API via `file_get_contents()`.
3. Inspected `ExternalApiService::fetchAnalyticsAggregate()`, confirmed no timeout was set. Any slow response from the external API would block a PHP-FPM worker
4. Reviewed `FormController::index()` and found N+1 query pattern: for each form, 3 separate DB queries were executed (`countByForm`, `getLatestSubmittedAt`, `User::find`). A user with 20 forms triggered 61 queries per request.
5. Connected the two findings to the symptom: under business-hours load, both issues compound — workers blocked on external API + DB connection pool exhaustion.

## How to Reproduce

1. Simulate concurrent requests to `/api/forms` with a user that has many forms (10+).
2. Simultaneously trigger `/api/forms/analytics` while the external mock API is slow.
3. Observe PHP-FPM worker pool saturation and request timeouts.

## Root Cause

Two independent issues compounding under load:

1. `ExternalApiService::fetchAnalyticsAggregate()` had no HTTP timeout — a slow external API response blocked PHP-FPM workers indefinitely. With enough concurrent users, all workers were occupied and the site stopped responding.

2. `FormController::index()` executed 3 DB queries per form (N+1 pattern). Under load, this exhausted the DB connection pool.

## Fix Description

1. Added `'timeout' => 5` to the HTTP stream context in `ExternalApiService.php` — workers now fail fast instead of blocking.

2. Added `Submission::statsForForms()` — a single `GROUP BY` query fetching counts and latest timestamps for all forms at once. Moved `User::find()` outside the loop. Total queries reduced from `1 + 3N` to `3`.

## Response to Reporter

> Hi,

> Thank you for letting us know. We identified two underlying issues whick causes the freezes during busy hours. One involved a connection to an external service that had no time limit — if that service was slow, it could cause our system to back up. The other was an inefficiency in how we loaded your forms list, which put extra pressure on the database under heavy traffic.

> Both issues have been fixed. The system should now handle peak hours without freezing. Please let us know if you notice anything else.