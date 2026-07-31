## 2025-02-18 - SARGable Queries for Date Ranges
**Learning:** In MySQL/MariaDB, wrapping columns in functions like `DATE(col)` in a `WHERE` clause (e.g., `WHERE DATE(col) BETWEEN ? AND ?`) renders the query non-SARGable, meaning the database cannot effectively use indexes on that column, resulting in full table scans.
**Action:** When filtering by date ranges on `DATETIME` or `TIMESTAMP` columns, always use index-friendly range conditions like `col >= ? AND col <= ?` and append `00:00:00` to the start date and `23:59:59` to the end date in PHP before executing the query.
