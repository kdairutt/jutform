# FEATURE-005: Analytics summary API

**Priority:** Low  
**Type:** New feature

## Description

We have a `form_metrics` table that our analytics pipeline populates daily. Product wants a summary API so the dashboard can display key numbers.

Build `GET /api/analytics/summary`. The response must contain the exact fields listed below.

### Table Schema

```sql
CREATE TABLE form_metrics (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  form_id         INT NOT NULL,
  date            DATE NOT NULL,
  views           INT NOT NULL DEFAULT 0,
  submissions     INT NOT NULL DEFAULT 0,
  avg_fill_time   DECIMAL(6,2) NOT NULL DEFAULT 0,
  country_code    CHAR(2) NOT NULL
);
```

### Required Response Format

```json
{
  "total_views": 12500,
  "total_submissions": 3200,
  "avg_fill_time_seconds": 47.32,
  "peak_day": "2024-01-15",
  "peak_day_submissions": 220,
  "latest_entry_date": "2024-03-20",
  "top_countries": [
    {"country_code": "US", "submissions": 1200},
    {"country_code": "GB", "submissions": 450},
    {"country_code": "DE", "submissions": 380}
  ]
}
```

### Field Definitions

| Field | Calculation |
|-------|-------------|
| `total_views` | `SUM(views)` across all rows |
| `total_submissions` | `SUM(submissions)` across all rows |
| `avg_fill_time_seconds` | `AVG(avg_fill_time)` rounded to 2 decimal places |
| `peak_day` | The `date` with the highest `SUM(submissions)` that day across all forms |
| `peak_day_submissions` | The total submission count on `peak_day` |
| `latest_entry_date` | `MAX(date)` |
| `top_countries` | Top 3 `country_code` values by `SUM(submissions)`, ordered descending |

## What is Provided

- The `form_metrics` table is already created and seeded with realistic data (multiple country codes, multiple dates, multiple forms)
- No external services or Redis required

## Acceptance Criteria

- Returns `HTTP 200` with `Content-Type: application/json`.
- All seven top-level keys are present in the response.
- All values match the seeded dataset.
- `top_countries` has exactly 3 entries, ordered by `submissions` descending.
- `avg_fill_time_seconds` is rounded to exactly 2 decimal places.

## Notes

This is primarily a SQL aggregation task; no external services required.
