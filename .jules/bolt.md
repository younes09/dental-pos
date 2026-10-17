## 2024-08-24 - SARGable Queries over `DATE()` wrapper
**Learning:** The analytics endpoints were using `DATE(s.date) BETWEEN ? AND ?` and similar constructs wrapping the timestamp column inside `DATE()`. While semantically correct, this is an anti-pattern as it makes the queries non-SARGable (Search ARgument ABLE). MySQL cannot use indexes efficiently when a column is wrapped in a function, leading to full table scans.
**Action:** Always format the date range criteria to be explicitly compared against the raw column values (e.g., `s.date >= ? AND s.date <= ?`), and append ` 00:00:00` and ` 23:59:59` to the bound parameters string appropriately in PHP before passing them to the execute function.

## 2024-08-24 - Missing Indexes on Frequently Queried Fields
**Learning:** The database schema was missing indexes on frequently queried fields like `sales(date, status)`, `customers(created_at)`, and `sale_items(sale_id)`. The lack of indexes combined with non-sargable queries caused full table scans.
**Action:** Add indexes on these fields to the database initialization script.
