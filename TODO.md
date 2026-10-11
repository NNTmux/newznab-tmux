# To do

## Turn the paused groups back on

On 2026-10-11 these groups were turned off (`active = 0`, `backfill = 0`) to cut collection churn while the backlog clears. Their files are posted with random names and random posters, so header-based grouping never completes a release. Together they held about 4.2M of the 14.1M collections, for about 270 releases over 50 MB a day.

| id | group | active | backfill | backfill_target |
|---|---|---|---|---|
| 97 | alt.binaries.coolkidweb | 1 | 1 | 3 |
| 98 | alt.binaries.faded-glory | 1 | 1 | 3 |
| 22 | alt.binaries.newznzb.golf | 1 | 1 | 3 |
| 23 | alt.binaries.newznzb.hotel | 1 | 1 | 3 |
| 24 | alt.binaries.newznzb.india | 1 | 1 | 3 |
| 25 | alt.binaries.newznzb.juliet | 1 | 1 | 3 |
| 85 | alt.binaries.superman | 1 | 1 | 3 |

The values in the table are what each group had before. To restore them:

```sql
UPDATE usenet_groups SET active = 1, backfill = 1 WHERE id IN (97, 98, 22, 23, 24, 25, 85);
```

Then clear the settings cache (`php artisan cache:clear` in the indexer container).

Their existing collections were left in place, not deleted. Headers missed while they're off can be recovered later by backfilling these groups.

## Revisit collection churn (deferred)

Options still on the table. None are applied yet, because big releases must not be lost:

- Delete one-file fragments of multi-file posts after about 15 minutes, while keeping the 2-hour delay for everything else. This needs a code change and a PR.
- Lower the collection delay from 2 hours to 1 hour for everything. The risk is that slow uploads get cut into incomplete releases.
- Filter random-name subjects at header ingestion. This is the most efficient option, but also the most likely to drop real posts.
- Decide about alt.binaries.wood: 1.44M collections for about 2.2k useful releases a day.

## Redesign rename backlog processing (implemented; deployment pending)

Approved and implemented on 2026-10-11 in this worktree. Deployment and production verification remain before increasing rename-worker concurrency. The goal is to recover useful release names while keeping incomplete and failed attempts eligible for retry.

Problems addressed in the implementation:

- `NzbContentsService::checkPar2()` calls `parsePAR2()` before checking the NZB subject, so it can download the first article of every entry before deciding whether it is a PAR2 candidate.
- Missing/unreadable NZBs and unsuccessful PAR2 attempts can set `proc_par2 = 1`. The grouped command also treats a failed NNTP connection as a false result that marks PAR2 processing complete.
- Long, unbounded PAR2 passes keep the rename pane occupied and delay the next database-only rename cycle.

Approved approach:

- [x] Select explicit PAR2 candidates before downloading; preserve a bounded fallback for obfuscated subjects.
- [x] Distinguish successful renames, completed checks without a match, and retryable failures; retain pending work after connection failures, timeouts, or unavailable NZBs.
- [x] Bound download batches and runtime so database-only rename checks run regularly. Verify fairness between recent releases and the historical backlog.
- [x] Test timeouts, missing articles, missing/corrupt NZBs, explicit and obfuscated PAR2 subjects, worker overlap, interruption/restart, and retry behavior in isolation with mocked NNTP.
- [x] Measure articles downloaded, actual names recovered, completed checks, retryable failures, and throughput. A lower pending count alone is not proof of successful recovery.
- [x] Complete affected tests, Pint, PHPStan, and PHP syntax checks before staging implementation. Full PHPStan has ten existing findings, reproduced against the unmodified base revision; changed runtime files analyse cleanly.
- [ ] Coordinate deployment with the designated deploying session, then validate a bounded production sample and observe actual rename yield, retry outcomes, download counts, and backlog trend.

Evidence from the investigation: the separate database-only catch-up reported 33 changed names. The two supplemental experimental workers were stopped before redesign; the existing production rename worker continued. The redesign was validated in an isolated container without production mounts or network access.

Validation: the complete PHPUnit suite ran 2,633 tests and 20,987 assertions with no failures, 93 skips, and 54 PHPUnit notices. Pint and PHP syntax checks passed. Full-project PHPStan reported the same ten findings reproduced on the unmodified base revision; no new findings remain. Independent review findings were fixed and regression-tested, including the real checker completing an unmatched release too early, stale success state between releases, unavailable candidates blocking recovery volumes, and retry starvation. Shared claims were also exercised across separate PHP processes.

Default limits: 100 releases per PAR2 pass, a 60-second soft budget, four candidate probes per release per pass, and a 2 MiB limit for hidden first-segment probes. Per-release probe offsets and retry queues persist in `par2_rename_progress`, with cascading release deletion and cleanup after committed renames. Shared cache claims coordinate workers; sweep and grouped cursors have no fixed expiry. Apply the new migration before starting the updated workers. An in-flight NNTP read may finish after the soft budget. Delivery proceeds through a reviewed PR; production deployment and live results remain pending.

PR review regressions also cover malformed hidden-probe byte counts, thrown download/parser errors, grouped retry fairness, progress retained beyond seven days, raw-deletion cascade, concurrent renames, and rollback-safe checkpoint cleanup. Maintenance reset commands also clear checkpoints without relying on disabled foreign keys. The affected suites passed 108 tests and 357 assertions after these corrections; final full-suite CI is recorded on the PR.

Relevant code: `app/Services/Nzb/NzbContentsService.php`, `app/Services/Par2Processor.php`, `app/Services/NameFixing/NameFixingService.php`, `app/Console/Commands/ReleasesFixNamesGroup.php`, and `app/Services/Tmux/TmuxTaskRunner.php`.
