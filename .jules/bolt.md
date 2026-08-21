## 2024-10-31 - SARGable Queries and Indexes
**Learning:** Using `DATE(date) BETWEEN ? AND ?` prevents MySQL from using indexes on the `date` column, resulting in full table scans. This is a common performance bottleneck in analytics endpoints that filter by date ranges.
**Action:** Always use SARGable range conditions (e.g. `date >= ? AND date <= ?`) and append specific start (`00:00:00`) and end (`23:59:59`) times to PHP variables. Combine this with composite indexes on `(date, status)` to significantly improve performance.
