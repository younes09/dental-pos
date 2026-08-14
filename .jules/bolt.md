## 2024-08-14 - Replace N+1 Correlated Subqueries with Derived Table LEFT JOINs
**Learning:** Correlated subqueries inside the `SELECT` clause cause severe N+1 query performance issues because the subquery must be executed for every row in the result set.
**Action:** Always rewrite correlated subqueries used for aggregation (like `SUM` or `COUNT` from related tables) as derived tables combined with `LEFT JOIN`s grouped by the foreign key to compute aggregations in a single pass before joining.
