# FEATURE-004: Rate limit public form submissions

**Priority:** Medium  
**Type:** New feature

## Description

Our form submission endpoint (`POST /api/forms/{id}/submissions`) is receiving spam from automated bots. Add rate limiting based on the requester's IP address.

Rules:

- Maximum **10 requests per 5 seconds** per IP address
- Requests that exceed the limit must receive `HTTP 429 Too Many Requests`
- The `429` response must include a `Retry-After` header indicating how many seconds remain until the limit resets

## Acceptance Criteria

- First 10 `POST /api/forms/{id}/submissions` requests from the same IP return `HTTP 201`.
- The 11th request within the same 5-second window returns `HTTP 429`.
- `Retry-After` is present on the `429` response and contains a positive integer.
- Requests to other endpoints (e.g., `GET /api/forms`) are not affected by the rate limit.

## Notes

Apply the rate limit only to the submission endpoint — not globally.
