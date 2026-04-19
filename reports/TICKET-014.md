# Investigation Report — TICKET-014: Duplicate rows when paging through submissions

## Root Cause

`ORDER BY submitted_at DESC` alone is non-deterministic when multiple submissions share the same timestamp. MySQL can return them in a different order on each query. When new submissions arrive during browsing, OFFSET shifts and the same row appears on two adjacent pages.

## Fix

Added `id DESC` as a tiebreaker: `ORDER BY submitted_at DESC, id DESC` — stable, unique ordering guaranteed.

## Response to Reporter

> Hi,

> The duplicates were caused by an unstable sort order when multiple submissions arrived at the same time. We've added a secondary sort key to guarantee consistent ordering across pages. You should no longer see the same entry on multiple pages.