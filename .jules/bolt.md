## 2026-09-26 - Optimize Analytics Queries (SARGable)
**Learning:** The database contains an index on `(status, date)` on the `sales` table which wasn't being utilized correctly because functions like `DATE(s.date)` were wrapping the indexed column, rendering conditions non-SARGable. Also needed to append time for exact range matching when dropping `DATE()`.
**Action:** Replaced `DATE(s.date) BETWEEN ? AND ?` with `s.date >= ? AND s.date <= ?` and handled the time component in PHP.
