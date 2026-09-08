## 2024-05-19 - Correlated Subqueries in SELECT

**Learning:** When retrieving aggregate counts or sums alongside primary table rows in MySQL (like fetching total orders and total spent for each customer or supplier in `api/reports.php`), using a correlated subquery in the `SELECT` clause (e.g. `(SELECT COUNT(*) FROM sales WHERE customer_id = customers.id)`) creates an N+1 performance bottleneck. MySQL must execute this subquery for every single row returned in the primary dataset.

**Action:** Replace the correlated subqueries with a `LEFT JOIN` on a derived table that groups by the foreign key (e.g., `LEFT JOIN (SELECT customer_id, COUNT(*), SUM(total) FROM sales GROUP BY customer_id) s ON c.id = s.customer_id`). Map nulls using `COALESCE` to ensure zero values are represented appropriately. This forces MySQL to only compute the aggregates once.
