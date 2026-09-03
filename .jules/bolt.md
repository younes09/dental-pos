
## 2024-09-03 - N+1 Correlated Subqueries in SELECT Clause
**Learning:** Correlated subqueries in a SELECT clause (e.g., fetching a customer's `last_visit` and `total_purchases` per row) cause massive performance regressions (N+1 queries) when iterating over large tables.
**Action:** Always rewrite correlated subqueries using a `LEFT JOIN` on an aggregated derived table (e.g., `GROUP BY customer_id`). When aggregating conditionally inside the derived table, use conditional aggregates like `SUM(CASE WHEN condition THEN value ELSE 0 END)` to apply filters per column rather than filtering the entire derived table.
