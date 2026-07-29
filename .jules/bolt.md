## 2024-05-24 - SARGable Date Queries Optimization
**Learning:** Wrapping indexed `DATETIME` or `TIMESTAMP` columns in functions like `DATE()` inside `WHERE` clauses (e.g., `WHERE DATE(date) BETWEEN ? AND ?`) prevents the database optimizer from using indexes, leading to full table scans. This is a critical performance bottleneck as the data grows.
**Action:** Always rewrite date queries to be SARGable by constructing explicit boundary strings in PHP (e.g., `$from . ' 00:00:00'` and `$to . ' 23:59:59'`) and querying against the raw column directly (e.g., `WHERE date BETWEEN ? AND ?`).
