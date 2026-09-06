## 2024-10-24 - SARGable Database Queries
**Learning:** In MySQL/MariaDB, wrapping column names in functions like `DATE(date)` within `WHERE` clauses disables the use of indexes (making the query non-SARGable) and forces full table scans.
**Action:** Always use SARGable range conditions like `date >= ? AND date <= ?` when querying `DATETIME` or `TIMESTAMP` columns. In PHP, append ' 00:00:00' to the start date and ' 23:59:59' to the end date to ensure inclusive bounds.
