## 2026-08-11 - Optimize correlated subqueries with LEFT JOIN derived tables
**Learning:** In this codebase, nested subqueries in SELECT clauses causes severe N+1 performance bottlenecks on report generation.
**Action:** Always prefer a LEFT JOIN on a single aggregated derived table over multiple SELECT subqueries when reporting aggregates from related tables.
