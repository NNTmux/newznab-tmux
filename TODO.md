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

## Redesign rename backlog processing (deployed; throughput follow-up remains)

Implemented, reviewed, merged in PR #57, and deployed on 2026-10-11. The checkpoint migration ran before workers restarted; all application services passed health and reviewed-file parity checks. Sustained yield and net backlog trend still need assessment before increasing rename-worker concurrency. The goal is to recover useful release names while keeping incomplete and failed attempts eligible for retry.

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
- [x] Complete affected tests, Pint, PHPStan, and PHP syntax checks before staging implementation. PR #57 PHPStan reported ten existing findings reproduced against its unmodified base; the cleanup below clears them in this follow-up worktree.
- [x] Deploy through the shared guard and validate live bounded batches, retained retry state, recovered names, and search-index synchronization.
- [ ] Assess sustained actual rename yield and net backlog trend against incoming releases before raising concurrency.

Evidence from the investigation: the separate database-only catch-up reported 33 changed names. The two supplemental experimental workers were stopped before redesign; the existing production rename worker continued. The redesign was validated in an isolated container without production mounts or network access.

Validation: the complete PHPUnit suite ran 2,633 tests and 20,987 assertions with no failures, 93 skips, and 54 PHPUnit notices. Pint and PHP syntax checks passed. Full-project PHPStan reported the same ten findings reproduced on the unmodified base revision; no new findings remain. Independent review findings were fixed and regression-tested, including the real checker completing an unmatched release too early, stale success state between releases, unavailable candidates blocking recovery volumes, and retry starvation. Shared claims were also exercised across separate PHP processes.

Default limits: 100 releases per PAR2 pass, a 60-second soft budget, four candidate probes per release per pass, and a 2 MiB limit for hidden first-segment probes. Per-release probe offsets and retry queues persist in `par2_rename_progress`, with cascading release deletion and cleanup after committed renames. Shared cache claims coordinate workers; sweep and grouped cursors have no fixed expiry. Apply the new migration before starting the updated workers. An in-flight NNTP read may finish after the soft budget. Delivery completed through [PR #57](https://github.com/xbmc4lyfe/newznab-tmux/pull/57); detailed production verification is in its [rollout record](https://github.com/xbmc4lyfe/newznab-tmux/pull/57#issuecomment-6114535667).

PR review regressions also cover malformed hidden-probe byte counts, thrown download/parser errors, grouped retry fairness, progress retained beyond seven days, raw-deletion cascade, concurrent renames, and rollback-safe checkpoint cleanup. Maintenance reset commands also clear checkpoints without relying on disabled foreign keys. The affected suites passed 108 tests and 357 assertions after these corrections; final full-suite CI is recorded on the PR.

Relevant code: `app/Services/Nzb/NzbContentsService.php`, `app/Services/Par2Processor.php`, `app/Services/NameFixing/NameFixingService.php`, `app/Console/Commands/ReleasesFixNamesGroup.php`, and `app/Services/Tmux/TmuxTaskRunner.php`.

## Remaining-work checklist (2026-10-11)

Work proceeds from small contract corrections to broader operational decisions. Completed means verification passed; changes in this follow-up worktree remain separate from deployed PR #57.

- [x] Clear the two search-cursor encryption contract findings while preserving existing tokens. `cursor_contract`: six tests / 15 assertions; full PHPStan clean.
- [x] Clear the two compressed-NNTP findings while preserving split-stream/terminator and EOF/error handling. `nntp_analysis`: five focused tests plus 17 related regressions pass; full PHPStan clean.
- [x] Clear the three movie lookup findings by using the Trakt provider's nullable result contract. `movie_null_fallbacks`: 20 tests / 48 assertions; full PHPStan clean.
- [x] Clear the remaining three findings: preflight index-name return type and obsolete User/Trakt suppressions. Preflight/Trakt tests pass; full PHPStan clean.
- [x] Finish the full regression suite and stage only this task's files. Pint, full PHPStan (zero errors), all-app/changed-test syntax lint, and independent source review pass.
- [x] Commit/push the follow-up corrections and open [PR #59](https://github.com/xbmc4lyfe/newznab-tmux/pull/59).
- [ ] Complete current-head CI and thorough independent/Codex review of PR #59; resolve any findings.
- [ ] Merge and deploy the reviewed follow-up corrections separately from PR #57.
- [ ] Compare rename completion with release arrival rate and determine why historical probes mostly defer. Keep checkpoints/retry eligibility intact while evaluating efficiency.
- [ ] Evaluate collection churn options above with evidence that large/slow uploads remain recoverable.
- [ ] Re-enable the seven paused groups only after the backlog/churn recovery conditions are met.

Live rename observation at this inventory: 17 completed bounded passes (nine recent, eight historical) report 22 recovered names and 2,433 received articles. Historical passes report two recovered names, one completed miss, five retryable outcomes, and 330 deferrals. These are reported outcomes, not evidence that the net backlog is shrinking. Continue distinguishing real recovered names from completed checks.

Next operational priority: inspect a bounded sample of existing historical NZBs and durable checkpoints before changing probe selection. The sampled historical runs deferred 330 of 338 attempts (97.6%) and averaged 3.92 received articles per attempt, close to the four-probe cap. Current logs combine probe-budget, deadline, and claim-loss deferrals, so the cause is not yet proven. Indexes precede all recovery volumes, then hidden candidates; repeated volume metadata may waste probes, but partial metadata and missing indexes still require fallback. Prefer evidence-led candidate prioritization before excluding volumes or adding workers. Regression gates must retain missing/corrupt-index volume recovery, partial/multiple recovery sets, obfuscated indexes, transient retries, restart/resume, changed NZBs, and worker overlap.

Monitor baseline for the next comparison (2026-10-11 22:46:54 UTC): the Renames row reports 465,835 unmatched and 24,317 matched. These monitor counts span all rename methods and must be assessed alongside actual changed-name/download outcomes.

Monitor comparison captured 2026-10-11 22:52:44 UTC: 465,869 unmatched (+34 versus baseline) and 24,334 matched (+17). In this short window names continue to recover, but unmatched rename work is still growing; measuring arrival rate and historical probe efficiency remains the next operational priority.

Follow-up correction verification: all ten PHPStan findings are cleared; full analysis of 849 files reports zero errors with no added baseline entries. The complete local PHPUnit suite passes 2,671 tests and 21,108 assertions, with 93 existing skips and 54 PHPUnit notices. Pint, PHP syntax lint, and independent source review pass. The correction batch is prepared on `codex/phpstan-backlog-cleanup` and remains separate from the deployed rename redesign. The correction batch is published in [PR #59](https://github.com/xbmc4lyfe/newznab-tmux/pull/59); current-head CI/review and release delivery are tracked above.

## Retest SQL contention repairs (2026-10-12)

Expedited shipping was requested with GitHub CI/CD skipped. Run the full regression suite and deployment qualification later; focused checks and live observations are recorded on each repair PR.

- [ ] Rerun the full PHPUnit suite, native decoder parity, and required CI checks for the expedited SQL repairs.
- [ ] Recheck rename-count equality across PAR2, metadata-only, missing-source, NZB-less, already renamed, and recent-only releases. The PAR2 fast path must skip metadata probes while preserving eligibility and counting a release once.
- [ ] Compare monitor query durations and scheduling gaps under the same workload, plus net rename backlog, actual recovered names, arrivals, and DB row-lock wait rates over an hour. The bounded SQL probe is not a sustained throughput benchmark.
