## 2024-10-27 - SARGable Date Queries
**Learning:** In MySQL, wrapping indexed columns in functions like `DATE(col) BETWEEN x AND y` prevents the use of indexes and causes full table scans.
**Action:** Always rewrite date filters to use raw column ranges like `col >= ? AND col <= ?` and append time boundaries (`00:00:00` and `23:59:59`) to the date string in PHP before binding to the query. Add a composite index on frequently queried filter conditions (like `status, date`).
