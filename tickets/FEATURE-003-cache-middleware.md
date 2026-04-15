# FEATURE-003: Cache reference data endpoints

**Priority:** Medium  
**Type:** New feature

## Description

Two of our reference data endpoints are queried on every page load but almost never change:

- `GET /api/field-types` — returns the list of supported form field types
- `GET /api/countries` — returns all country codes and names

They are currently hitting the database on every request. Add a caching layer to these endpoints. The cache should expire after **5 minutes**. If the same data is requested again within that window, it should be served from cache without touching the database.

## Acceptance Criteria

- First request to `GET /api/field-types` queries the database and returns correct JSON.
- Second request within 5 minutes returns identical JSON without querying the database.
- After the cache window expires, the next request queries the database again.
- Both `/api/field-types` and `/api/countries` are cached.

## Notes

Scope the cache to the intended endpoints only — do not apply it globally.
