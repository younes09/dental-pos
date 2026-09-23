## 2024-03-20 - Subquery optimization limitations with filtered outer queries
**Learning:** To optimize MySQL performance for unbounded list endpoints (fetching all rows), replace N+1 correlated subqueries with a `LEFT JOIN` on a derived table grouped by the foreign key (e.g., `LEFT JOIN (SELECT fk, SUM(val) FROM t GROUP BY fk)`). Ensure the derived table is appropriately indexed, and use `COALESCE` in the main SELECT to handle NULLs from the `LEFT JOIN`. However, for dynamically filtered queries (e.g., `WHERE customer_id = ?`), standard SQL engines cannot push the filter into the derived table, causing full table scans. In such filtered cases, retain indexed correlated subqueries instead.
**Action:** Be extremely cautious when optimizing filtered endpoints; derived tables can introduce massive regressions. Stick to standard joins or correlated subqueries for filtered scenarios.

## 2024-03-21 - Optimal Indexing for Status/Date Queries
**Learning:** The `sales` table is frequently queried using date ranges and status filters (e.g., `date >= ... AND status = 'Completed'`). It relies on a composite index on `(status, date)` in `database/dental_pos.sql` (ordering the equality check `status` before the range check `date` for optimal query planner usage) to prevent full table scans.
**Action:** Always ensure that queries combining equality and range filters match an index with the equality column preceding the range column.

## 2024-03-22 - Left Join Regressions on Aggregated Derived Tables
**Learning:** When optimizing SQL queries involving `LEFT JOIN`s on aggregated derived tables (e.g., `sale_items`), ensure the derived table includes its own date-filtering `WHERE` clause (mirroring the outer query) to prevent full table scans and severe performance regressions.
**Action:** If a derived table is used, push the WHERE filters (like date ranges) down into the derived table query. Even better, replace the derived table with a direct join if aggregate deduplication (like `COUNT(DISTINCT)`) can handle the multiplicity safely.
