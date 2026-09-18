## 2024-06-25 - SARGable Queries and Indexes
**Learning:** `WHERE DATE(s.date) BETWEEN ? AND ?` and similar conditions using functions on indexed columns prevent MySQL from using the index effectively, resulting in full table scans.
**Action:** Always rewrite range queries on datetime/timestamp columns to use SARGable bounds (e.g. `s.date >= ? AND s.date <= ?`), adding time segments in PHP (like ' 00:00:00' and ' 23:59:59') to mimic `DATE()` behavior, and ensure an index covering the filtered columns exists.
