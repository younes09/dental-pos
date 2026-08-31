## 2024-05-18 - Fix N+1 subqueries in customer reports
**Learning:** Correlated subqueries in the SELECT clause (e.g., `(SELECT COUNT(*) FROM sales WHERE customer_id = customers.id)`) cause N+1 query problems resulting in significant performance bottlenecks (e.g., 0.027s vs 0.007s).
**Action:** When aggregating data for related records, use a `LEFT JOIN` on a derived table grouped by the foreign key (e.g., `LEFT JOIN (SELECT customer_id, COUNT(*) FROM sales GROUP BY customer_id) s`) and `COALESCE` the results to 0 to properly handle absent relationships without performance penalties.
