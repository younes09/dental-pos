## 2026-07-23 - Make Analytics Date Queries SARGable
**Learning:** The analytics API evaluates `DATE(s.date)` on every row, which prevents index usage and requires a full table scan.
**Action:** Convert `DATE(column) BETWEEN ? AND ?` to `column >= ? AND column <= ?` and manually append time bounds (`00:00:00` and `23:59:59`) to the date strings in PHP.
