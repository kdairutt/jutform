# Investigation Report — TICKET-012: Security review — advanced search endpoint

## Debugging Steps

1. Located `SearchController::advancedSearch()` following the security team's report.
2. Reviewed query construction — found `$field` parameter was taken directly from user input (`request->query('field')`) and interpolated into the SQL query as a column name: `WHERE {$field} LIKE ?`.
3. Confirmed PDO prepared statements cannot parameterize column names — only values. The `$field` parameter was therefore vulnerable to SQL injection regardless of the prepared statement used for `$term`.
4. Also reviewed `$term` handling — found it was being escaped manually via `str_replace("'", "''", $term)` and injected into a raw SQL string. This is an unreliable sanitization approach and a second injection vector.
5. Reviewed `SearchController::search()` — found it queries `app_config` table which may contain sensitive system configuration. Confirmed this endpoint is accessible to any authenticated user without further scoping.

## How to Reproduce

1. Old code would interpolate `$field` directly into the query, allowing arbitrary SQL injection.

## Root Cause

Two issues:

1. `$field` (column name) was interpolated directly into SQL without validation — PDO placeholders do not protect column names, only values.
2. `$term` was sanitized via manual string escaping instead of prepared statements — unreliable and bypassable.

## Fix Description

- Added `$allowedFields = ['title', 'description', 'slug']` whitelist.
- Added `in_array($field, $allowedFields, true)` check — returns `400` if field is not whitelisted.
- Replaced raw SQL string construction with a proper prepared statement using `?` placeholders for `$term` and `$uid`.

## Response to Reporter

> Hi,
>
> Thank you for flagging this. The advanced search endpoint was constructing SQL queries using unvalidated user input for the column name parameter, which is not protected by prepared statements. A second issue involved manual string escaping for the search term instead of parameterized queries.
>
> Both have been remediated. Column names are now validated against a strict whitelist, and all user-supplied values are passed via prepared statement placeholders. The fix has been committed and deployed.