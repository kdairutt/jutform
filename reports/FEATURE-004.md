# Investigation Report — FEATURE-004: Rate limit public form submissions

## Debugging Steps

1. Reviewed submission endpoint — `POST /api/forms/{id}/submissions` had no rate limiting, fully open to automated requests.
2. Confirmed Redis is available in the stack — ideal for atomic counters with TTL.
3. Reviewed Router middleware structure to understand how to apply middleware per-route rather than globally.
4. Verified `Request::ip()` exists for IP extraction.
5. Tested implementation with 15 concurrent requests — first 10 return 201, 11+ return 429 with `Retry-After` header.

## Root Cause

No rate limiting on the public submission endpoint — bots could submit indefinitely without restriction.

## Fix Description

Implemented `RateLimitMiddleware` using a fixed-window counter in Redis:

- Key: `rl:sub:{ip}:{window}` where window = `intdiv(time, 5)` — new key every 5 seconds
- `INCR` is atomic — no race condition under concurrent load
- First hit sets TTL to 6 seconds — key auto-expires and resets
- Count > 10 → HTTP 429 + `Retry-After: N` header (seconds until window resets)
- Middleware applied only to `POST /api/forms/{id}/submissions` — no other endpoints affected

## Response to Reporter

> Hi,

> We've added rate limiting to the form submission endpoint. Each IP address is now limited to 10 submissions per 5-second window. Requests exceeding this limit will receive a 429 response with a header indicating when they can retry. Legitimate users submitting forms manually will not be affected.