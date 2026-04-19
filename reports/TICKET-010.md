# Investigation Report — TICKET 010: Webhook URL validation and internal endpoint access controls

## Debugging Steps

1. Reviewed `security.php`. found `isLocalRequest()` only blocked `127.0.0.1` and `localhost`. AWS metadata service (`169.254.169.254`), all RFC-1918 ranges, and DNS-based bypasses were not covered.
2. Found `isInternalRequest()` was reading `X-Forwarded-For` header to determine request origin. This header is client-controlled — any external request could spoof it with `X-Forwarded-For: 10.0.0.1` to bypass internal access controls.
3. Confirmed `AdminController::internalConfig` endpoint relied on `isInternalRequest()` — meaning it was accessible from the public internet via header spoofing.
4. Identified `defaultGatewayIp()` as dead code after the fix — removed it.

## How to Reproduce

**SSRF bypass:**
1. Set a webhook URL to `http://169.254.169.254/latest/meta-data/` (AWS metadata service).
2. Old validation would pass, server would make the request and expose internal metadata.

**Auth bypass:**
1. Send any request to an internal endpoint with header `X-Forwarded-For: 10.0.0.1`.
2. Old `isInternalRequest()` would return `true`, granting internal access to any external caller.

## Root Cause

Two issues in `security.php`:

1. `isLocalRequest()` only checked for `127.0.0.1` and `localhost` — insufficient. Many internal/private address ranges were exploitable, and DNS rebinding attacks were not mitigated.

2. `isInternalRequest()` trusted `X-Forwarded-For` header which is entirely client-controlled. `REMOTE_ADDR` is the only reliable source for the actual TCP connection origin.

## Fix Description

1. Rewrote `isLocalRequest()` — now resolves hostnames to IP via `gethostbyname()` (prevents DNS rebinding), then checks against all RFC-1918 ranges, loopback block, link-local, and reserved ranges using integer range comparisons.

2. Rewrote `isInternalRequest()` — now uses only `REMOTE_ADDR`. Removed `X-Forwarded-For` logic entirely. Removed unused `defaultGatewayIp()`.

## Response to Reporter

> Hi,
>
> Thank you for flagging this — it was a valid concern. We found two issues: our webhook URL checks were not blocking all internal network ranges, and one of our internal access controls could be bypassed by manipulating a request header.
>
> Both have been fixed. Webhook URLs are now validated against a comprehensive blocklist, and internal endpoints now verify the actual network origin of the request rather than trusting client-supplied headers.
>
> Please don't hesitate to reach out if you spot anything else during your review.