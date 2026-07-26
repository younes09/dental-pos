## 2024-07-26 - SARGable Queries over Functional Indexes
**Learning:** Found an anti-pattern where `DATE(column)` was being used in WHERE clauses to filter by a date range, which prevents the MySQL database from utilizing existing indexes on the `date` or `created_at` columns, leading to full table scans.
**Action:** Always format input bound values to inclusive datetime bounds (e.g. `$date . ' 00:00:00'` and `23:59:59`) and compare directly against the column instead of wrapping the column in a function.
