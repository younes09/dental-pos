## 2024-05-24 - Missing indexes on date-filtered queries
**Learning:** The `sales` table lacked an index on `(date, status)`, causing full table scans for dashboard queries which heavily filter on these columns.
**Action:** Added a composite index `idx_sales_date_status` to optimize range queries on `date` with `status` filtering.
