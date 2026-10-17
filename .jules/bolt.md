## 2024-08-12 - N+1 Issue in Reports API
**Learning:** Using correlated subqueries in SELECT clauses (e.g., `(SELECT COUNT(*) FROM sales WHERE customer_id = customers.id) as total_orders`) causes N+1 query problems and scans the subquery table for every row in the outer table, leading to O(N*M) time complexity.
**Action:** Refactor the correlated subquery into a LEFT JOIN with a derived table grouped by the foreign key to ensure O(N) execution time, which allows the database to aggregate once and join.
