# Investigation Report — TICKET-008: Revenue totals do not match finance records

## Root Cause

`AdminController::revenue` was parsing `amount` from JSON using `LOCATE` + `SUBSTRING`. When `amount` is the last field in the JSON object, `LOCATE(',', value, pos)` returns 0, producing a negative `SUBSTRING` length → `NULL`. Those rows were silently dropped from `SUM`. Field order varies by encoder, so some records were miscalculated and others excluded entirely.

## Fix

Replaced the string parsing with `JSON_EXTRACT(value, '$.amount')` — one line, correct regardless of field order.

## Response to Reporter

> Hi,
>
> The discrepancy was caused by a fragile method of reading the amount field from stored data — it failed silently when the field appeared in a certain position. We've replaced it with a proper JSON extraction function. Totals should now match your records exactly. Please let us know if any further discrepancies appear.