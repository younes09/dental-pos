## 2024-05-24 - Missing Sales Index
**Learning:** The dashboard frequently queries the `sales` table using a composite filter on `date` and `status` (`s.date >= ... AND s.date < ... AND s.status = 'Completed'`). Without an index on these columns, the database has to perform a full table scan.
**Action:** Add a composite index on `(date, status)` to `database/dental_pos.sql`.
