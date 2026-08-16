## 2024-08-16 - Derived Table LEFT JOINs for Bulk Operations
**Learning:** Correlated subqueries in the SELECT clause (which run per-row) for aggregation calculations caused a massive performance degradation as rows scaled (O(n)).
**Action:** Instead of aggregating per-row with correlated subqueries or running N+1 queries, perform bulk aggregation grouped by the foreign key in a derived table and `LEFT JOIN` the result to the main query to take advantage of MySQL's query optimizer and bulk computation efficiencies.
