## 2024-05-19 - Analytics SARGable Query Fix
**Learning:** In MySQL, wrapping a `TIMESTAMP` or `DATETIME` column in a function like `DATE(column)` disables the usage of range indexes, leading to full table scans. This codebase's `api/analytics.php` relies heavily on range queries over the `sales` and `customers` tables.
**Action:** Replace `DATE(column) BETWEEN ? AND ?` with `column >= ? AND column <= ?` and append `00:00:00` and `23:59:59` to the start and end dates respectively. Ensure composite index `(status, date)` exists on the `sales` table, and `(created_at)` on `customers`.
