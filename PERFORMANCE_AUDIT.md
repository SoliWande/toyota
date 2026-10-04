# Performance audit — 2026-10-04

## Scope and method

Reviewed SPEC.md, leaderboard service, submission lists, Admin/Sales dashboards, award preview, their Blade views, pagination internals in the installed Laravel framework, and existing schema/indexes.

Measured rendered HTTP response query counts using MySQL feature tests. Examined EXPLAIN plans with 10,000 synthetic submissions, 20 Sales and their Dealers in the isolated `toyota_testing` database. Synthetic records were removed after inspection; no development customer records were changed. Optimizer statistics were refreshed on committed test fixtures for the final plans. EXPLAIN row counts are estimates, not measured response times or a production load test.

## Findings and changes

| Area | Finding | Action |
| --- | --- | --- |
| Leaderboard | SQL COUNT/MAX aggregates, joins and ROW_NUMBER ranking; no per-entity queries. Week/month use status + submitted_at range index. Laravel pagination strips outer selected columns/order from its count query, including the window expression. | Retained existing service and queries. Temporary grouping/ranking sorts are expected; no speculative covering indexes or cache added. |
| Admin submission list | Page size 20; Sales/Dealer already eager loaded. Duplicate warning uses correlated lookup against the unique approved identity index, not one application query per row. Related Sales previously selected every column. All-status ordered list lacked a submitted_at-leading index. | Narrowed eager loading to Sales id/name/dealer_id and Dealer id/name. Added `submissions_time_index (submitted_at)`. |
| Sales submission list | Page size 20, owner scoped, no relation queries. Existing `(sales_id,status,submitted_at)` cannot supply date order when status is not fixed. List loaded unused URL, notes, evidence and review fields. | Added `submissions_sales_time_index (sales_id,submitted_at)`. Select only id/sales_id/customer_name/status/submitted_at. |
| Sales dashboard | Grouped counts, eager Dealer, five recent records. Global rank calculation ran even with zero approved submissions, when the result must be null. | Skip ranking query for zero approved submissions; reuse existing aggregate count. New Sales time index also serves recent records. |
| Admin dashboard | Eight queries with data: grouped Sales counts, one conditional submission aggregate, Dealer count, recent pending + two eager relations, two top-three ranking queries. | Retained existing bounded/eager loading and aggregates. The all-time aggregate must still read history for exact totals; splitting it adds work without evidence of benefit. |
| Awards | Preview uses two leaderboard aggregate queries in a read transaction, LIMIT 3 per type; no customer loading or per-winner queries. Published display reads snapshots. | Retained calculation. Publication workflow is not implemented in the current project and was not added during this audit. |

Both new InnoDB secondary indexes implicitly include the primary key id, supporting the existing same-direction submitted_at/id tie-break order. Existing status/time and Sales/status/time indexes remain useful for filtered lists, counts and leaderboard ranges. The new indexes incur additional storage and index maintenance on inserts; no duplicate approved identity constraints were changed.

## Query plan evidence

| Query, LIMIT 20 | Before | After |
| --- | --- | --- |
| Sales list, no status | `submissions_sales_status_time_index`, `Using filesort`, estimated 500 matching rows | `submissions_sales_time_index`, backward index scan, no filesort |
| Admin list, all statuses | Table scan, no key, `Using filesort`, estimated 10,000 rows | `submissions_time_index`, index scan, estimated 20 rows, no filesort |
| Sales list, pending | Existing Sales/status/time index, backward index scan | Existing index retained, no filesort |
| Admin list, pending | Existing status/time index | Existing index retained, no filesort |
| Monthly Sales leaderboard | Status/time range + primary-key joins; grouping/ranking sorts | Same range strategy retained |

The optimizer may choose another plan on small tables or different distributions. No FORCE INDEX hints were added. Development migration does not run ANALYZE TABLE automatically.

## Regression coverage

`tests/Feature/PerformanceQueryTest.php` checks rendered Admin queue pages with 20 distinct Sales/Dealers (6 queries on both first/later page), paginated Sales list (2), Sales dashboard without/with approved records (3/4), and weekly/monthly award preview (2 aggregate queries, three winners per type).

Existing tests also enforce public leaderboard 4 queries on page one / 6 on later pages, Admin dashboard 8 queries, ranking tie-break/timezone/boundary rules, filters, pagination, ownership and data privacy.

Validation completed: full PHPUnit suite passed with **432 tests / 2,573 assertions**; Pint passed on all five changed PHP files; `git diff --check` passed. Migration ran successfully in development and testing, and both new indexes were verified present and visible in the development schema. No frontend asset changes required a new build.

## Remaining considerations

- Contains search uses escaped `LIKE '%term%'`; ordinary B-tree text indexes would not fix it. Keep current behavior until measured search traffic/data volume justifies another approach.
- Admin filter selects currently load all Dealer/Sales id/name options. This is a bounded query count but an unbounded option payload; consider searchable remote options if actual counts make the form slow. No new UI architecture introduced here.
- Exact counts, global ranking and deep OFFSET pages grow with history. No Redis/cache, materialized totals or cursor pagination added without workload evidence or a requirement change.
- This audit verifies query structure and regressions; it does not establish production throughput or latency targets.
