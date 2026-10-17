## 2024-07-30 - SARGability in MySQL Queries
**Learning:** Using functions like `DATE()` on indexed columns (e.g., `DATE(s.date) BETWEEN ? AND ?`) in `WHERE` clauses makes the query non-SARGable, preventing MySQL from using indexes efficiently and leading to full table scans.
**Action:** Always construct datetime range queries by appending `00:00:00` to the start date and `23:59:59` to the end date (e.g., `s.date >= ? AND s.date <= ?`), allowing MySQL to use indexes on the datetime column. Ensure this is applied consistently across all analytics and reporting queries.
