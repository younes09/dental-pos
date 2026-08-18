## 2024-08-18 - Database Index for SARGable date queries
**Learning:** Optimizing queries to use SARGable ranges (`date >= ? AND date <= ?`) instead of `DATE(date)` wrapper functions is important, but without an index on `(date, status)`, the query still falls back to a full table scan.
**Action:** Always verify if indices exist for columns frequently used in SARGable `WHERE` ranges and add them if they don't, especially when replacing function-wrapped queries.
