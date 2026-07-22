## 2024-05-15 - SARGable Queries in Analytics
**Learning:** Found multiple instances of `DATE(date_column) BETWEEN ? AND ?` in `api/analytics.php`. This prevents MySQL from using indexes on the date column, leading to full table scans. This is a common codebase-specific performance pattern since it's the primary way analytics are filtered.
**Action:** Replaced `DATE(column) BETWEEN ? AND ?` with index-friendly SARGable queries: `column BETWEEN ? AND ?` where the upper bound has `' 23:59:59'` appended.
