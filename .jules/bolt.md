## 2024-05-24 - SARGable Date Queries
**Learning:** The analytics queries in this codebase often wrap the timestamp `date` column in `DATE()` functions within `WHERE` clauses (e.g., `WHERE DATE(s.date) BETWEEN ? AND ?`). This is an anti-pattern that prevents MySQL from using range indexes (SARGability), causing full table scans.
**Action:** Replace `DATE(column) BETWEEN` with `column >= ? AND column <= ?` and manually append ' 00:00:00' and ' 23:59:59' to the bounds in PHP to maintain inclusive logic while allowing index usage.
