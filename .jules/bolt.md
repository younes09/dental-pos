## 2026-08-06 - N+1 Queries with Subqueries in SELECT
**Learning:** Correlated subqueries in the `SELECT` clause execute once for every row returned by the main query, causing a severe N+1 performance bottleneck, especially on list endpoints.
**Action:** Replace correlated subqueries in `SELECT` with a `LEFT JOIN` on a derived table grouped by the required foreign key, allowing the database engine to optimize the aggregation.
