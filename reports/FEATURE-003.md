# Investigation Report — FEATURE-003: Cache reference data endpoints

## Debugging Steps

1. Reviewed `/api/field-types` and `/api/countries` — both hit DB on every request, no caching.
2. Confirmed Redis available in the stack.
3. Attempted `ob_start()` + `register_shutdown_function` approach — failed in PHP-FPM because buffer is already flushed by shutdown time.
4. Switched to `ob_start(callback)` — callback fires during buffer flush, which happens even when `exit()` is called. Successfully captures response body and writes to Redis.

## Root Cause

No caching on reference data endpoints. Both queried the database on every page load despite data rarely changing.

## Fix Description

- `CacheMiddleware` — on cache hit, serves Redis response and exits. On cache miss, wraps response in `ob_start(callback)` to capture and store in Redis with 300s TTL.
- Applied only to `/api/field-types` and `/api/countries` via route-level middleware.
- Key format: `cache:{uri}` — scoped per endpoint.

## Response to Reporter

> Hi,

> Both `/api/field-types` and `/api/countries` are now cached for 5 minutes. The first request fetches from the database as before; subsequent requests within the window are served directly from cache. Database load on these endpoints has been eliminated for the cache window duration.