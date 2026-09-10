
## 2024-05-18 - Avoid Correlated Subqueries in SELECT lists
**Learning:** Correlated subqueries in the `SELECT` clause cause severe N+1 query execution problems in MySQL because the subquery must be evaluated for every single row returned by the main query.
**Action:** Replace correlated subqueries with a single `LEFT JOIN` against a derived table grouped by the required identifier. When multiple aggregated stats are needed (e.g. max date, conditional sums), they can be calculated in a single pass over the child table within the derived table and mapped cleanly to the main results using `COALESCE`.
