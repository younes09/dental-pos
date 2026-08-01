## 2024-05-24 - Database queries SARGability
**Learning:** Using `DATE(column)` in `WHERE` clauses on indexed datetime/timestamp columns prevents the database from using indexes (non-SARGable query). The memory provides guidance to use `column >= ? AND column < ?` ranges instead.
**Action:** Review database queries and replace `DATE(column) BETWEEN ? AND ?` with `column >= ? AND column < ?` or similar range queries where applicable to improve query performance.
