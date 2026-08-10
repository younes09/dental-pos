## 2024-05-18 - [Optimize N+1 query]
**Learning:** Correlated subqueries in the `SELECT` clause (e.g., executing a `SUM` or `COUNT` from a sub-table per row) can cause severe N+1 performance bottlenecks in raw PDO implementations, particularly on endpoints fetching history or lists where dataset size grows.
**Action:** Replace correlated subqueries with a `LEFT JOIN` on a derived table grouped by the foreign key (e.g., `LEFT JOIN (SELECT sale_id, COUNT(*) as c FROM sale_returns GROUP BY sale_id) sr ON sr.sale_id = s.id`) to aggregate data in a single pass.
