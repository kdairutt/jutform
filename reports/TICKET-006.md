# Investigation Report — TICKET-006: Form search is extremely slow

## Root Cause

The `app_config` table had 3.8M rows with no index on `value`. Every search ran `WHERE value LIKE '%term%'` — a full table scan. Leading wildcards prevent B-tree index use. Under concurrent load, all PHP-FPM workers blocked simultaneously, which explains why "refreshing makes it worse."

## Fix

1. Added `FULLTEXT INDEX ft_app_config_value (value)` via migration 0004.
2. Updated query to `MATCH(value) AGAINST ('+term*' IN BOOLEAN MODE)`.

Post-fix EXPLAIN: `type: fulltext`, `rows: 1` — full scan eliminated.

## Response to Reporter

> Hi,
>
> The search slowness was caused by a missing database index — every search was scanning millions of rows from scratch. We've added a full-text index and updated the query accordingly. Search should now return results in milliseconds. Sorry for the frustration this caused.