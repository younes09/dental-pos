## 2024-08-17 - Optimize Analytics Date Queries by Avoiding SARGability Violations
**Learning:** In MySQL/MariaDB, using functions like `DATE(s.date)` in a `WHERE` clause on indexed columns (like `date` or `created_at`) violates SARGability and prevents index usage, leading to full table scans.
**Action:** Instead of wrapping indexed columns in `DATE()`, format the parameter boundaries using PHP (e.g. appending ' 00:00:00' and ' 23:59:59') so the query becomes a simple range check `WHERE s.date BETWEEN ? AND ?`, unlocking index utilization.
