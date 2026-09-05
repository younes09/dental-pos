## 2023-11-09 - Optimize Sales History Query
**Learning:** Avoid correlated subqueries in `SELECT` clauses as they cause an N+1 issue.
**Action:** Use `LEFT JOIN` on aggregated derived tables (`GROUP BY` on the foreign key) to resolve the N+1 problem. Use `COALESCE` to handle null mappings.
