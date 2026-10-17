## 2024-05-24 - SARGable Queries
**Learning:** Wrapping indexed columns in functions like DATE() in WHERE clauses (e.g. `WHERE DATE(date) BETWEEN ? AND ?`) breaks index usage (SARGability) and forces full table scans in MySQL/MariaDB. This is a common performance bottleneck in this codebase's analytics and reporting queries.
**Action:** Always use index-friendly range conditions for datetime filtering, such as `column >= ? AND column <= ?`, by manually formatting the PHP date strings with inclusive bounds (appending ' 00:00:00' for start and ' 23:59:59' for end).
