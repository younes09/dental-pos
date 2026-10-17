## 2026-08-08 - [SARGable Date Queries]
**Learning:** Using `DATE()` functions on `DATETIME` or `TIMESTAMP` columns in `WHERE` clauses prevents the database from using indexes effectively. This was a prevalent anti-pattern in the codebase.
**Action:** Always append time bounds (e.g., `00:00:00` and `23:59:59`) to date strings in PHP, and use range conditions (e.g., `date >= ? AND date <= ?`) in SQL queries to ensure they remain SARGable and index-friendly.
