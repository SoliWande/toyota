# Dealer leaderboard and awards

## Scoring

The leaderboard has one ranking: Dealers, for the current Monday-based week, calendar month, or all time. It counts approved submissions grouped by `customer_submissions.dealer_id`, without joining login accounts. Archived Sales submissions count only when their historical Dealer snapshot is known. NULL ownership remains excluded and is never inferred from an account's current Dealer.

Periods use application-timezone `submitted_at` values, with inclusive start and exclusive end. Review time does not move points into another period. Inactive Dealers and blocked accounts retain their approved historical points. Ranking is consecutive and deterministic: score descending, `MAX(submitted_at)` ascending, Dealer ID ascending. Queries expose only Dealer ID, name, code, score and rank.

## Publication

Admin creates or reopens the unique weekly/monthly period and views its Dealer Top 3 preview. Preview writes no winners. The admin preview page records the fingerprint in the session; the publish POST requires that fingerprint. New results contain at most three Dealers with a positive score, without invented winners when fewer Dealers qualify. An empty qualifying period can be published with no winners.

`PublishDealerAward` locks and reloads the admin and award, checks that the period has ended, then locks all submissions in `[period_start, period_end)` before checking pending and recomputing scores. Every pending submission in the period blocks publication, including records with unknown historical ownership. Pending outside the period does not block it. Dealer names/codes are also locked while taking the snapshot. The implementation uses the existing MySQL/InnoDB transaction and next-key locking behavior.

Publication rejects legacy scopes, previously published periods, malformed calendar bounds, existing draft snapshots and stale previews. If results changed, the admin must view a new preview. Winner insertion and publication fields commit together; failures roll back the entire snapshot. Concurrent publishers serialize on the award row. The existing `awards_period_unique` and winner uniqueness indexes provide additional database protection.

Published winner IDs, names, codes, ranks and scores are snapshots. Later submission approvals or Dealer renames do not change them. Existing model guards and database triggers preserve published awards and winners. New Sales winners are rejected by both model and database. Public and admin history continue rendering published Sales snapshots under the historical Sales heading, without recalculating them.

## Validation and data safety

`LeaderboardServiceTest`, `PublicLeaderboardTest`, `AwardWorkflowTest` and `AwardPublicationRaceTest` cover scoring, legacy attribution, inactive Dealers, privacy, pagination, timezone/calendar boundaries, late approvals, tie-breaking, preview requirements, period closure, pending guards, rollback, immutable snapshots and two real concurrent publishers.

Tests restore the verified backup into uniquely named MySQL test databases before applying the existing Dealer-only migration. They do not migrate or mutate the source database, backfill historical Dealer IDs or alter evidence. No new migration is needed for this change. The legacy Sales-oriented demo-account seeding workflow is not part of these tests; its award path now uses the same Dealer-only publication action and pending guard.
