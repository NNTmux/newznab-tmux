# CollectionHandler candidate benchmark protocol

Status: historical pre-execution protocol prepared offline on October 11, 2026. Its pending-work statements describe that preparation checkpoint, not current status. The handoff, semantic/plan gates and paired execution subsequently completed; see the [initial screen](collection-primary-screen-results.md), [final fixed-sample validation](hot-workload-validation.md), and [durable raw evidence](performance/evidence/raw/2026-10-11-publication-manifest.json). The final study's grouped epochs and pinned CPU profile supersede the initial screening schedule for the nonregression decision.

## Executable preparation completed

Private draft artifacts under the existing `newznab-hotpath-bench-current` pointer:

- `bench-paired-case.php`: one observation per fresh process; source gates, CPU snapshots, exact fixture-part oracle, worker-artifact checks and bounded joins.
- `run-paired.py`: dry-run default; explicit green/frozen-source/runtime-release gates; immutable images, full-source/vendor/schema/runner/order identities; fixed 48-observation coverage; timeout log preservation and bounded cleanup evidence.
- `paired-order.json`: seed 4711, 24 exact pairs/48 unique observations.
- `paired-handoff-template.json`: launch gates remain false until the owner hands off.
- `paired-baseline` and `paired-candidate`: derived from the owner's `54c140d7` source archive, with only CollectionHandler differing; complete source manifests preserved. The candidate copy remains provisional until final formatting/source confirmation.

These are private draft tools, not supported repository commands. Docker syntax, Python parsing and the nonexecuting dry run pass. Their database behavior and paired observations remain unexecuted. Static audit refinements distinguish expected baseline lock exhaustion from child crashes/unclassified failures and freeze the entire runner/order/source identity, not just the PHP file.

Before the first observation for each source variant, draft semantic gates use a small complete fixture. One observer connection deletes a parent after the real plain prefetch; the driver requires actual error 1452, at least two collection upsert attempts, complete committed contents/counters/readiness/crossposts and no final failed articles. A second probe throws after a real parts write, requires an empty rolled-back child/parent state and full failed-chunk accounting, then independently verifies replay. Both occur outside the timed interval and must pass before matrix acceptance. Runtime results, including whether the instrumentation captures the intended race, are still pending.

The historical database storage is explicitly tmpfs. The matched comparison preserves it; real-storage/durable-commit testing is a separate profile. Do not describe this matched screen as real-storage capacity evidence.

## Handoff required before execution

Validate test-environment completeness separately from candidate correctness. The reduced source export omitted repository files that full-suite tests inspect, including Docker/build/frontend/monitoring configuration; the native PHP environment lacked GD/EXIF, and a Node-driven test lacked Node. Those observed bootstrap/fixture failures require a complete safe tracked-source environment and appropriate test tooling, not changes to the collection query. The designated executor owns that repair and rerun. Do not silently skip the failures or call a repaired environment a proven candidate until checks pass.

For two-client regression tests, clear inherited database URL/socket overrides and set one explicit isolated destination/port for both Laravel and child PDO before creating any database. Preserve the random database ownership flag and drop only the schema this test created. Refresh the test hash and rewatch the relevant regression after changing connection setup.

Require the exact baseline and formatted candidate file contents and hashes, source revision/tree manifest, the actual regression result, affected tests, Pint/lint/PHPStan results, schema provenance, cached runtime image identities, and confirmation that the designated executor has released the runtime. A hash announced before formatting is provisional.

Only CollectionHandler may differ between the paired application sources. Compare the full manifests and retain any common upstream changes as a new baseline, rather than mixing the old report with a newer candidate. Do not use the historical timing samples as one side of a fresh paired comparison.

## Schema profiles are separate experiments

1. **Historical fixture:** unchanged six-table `schema.sql` from the frozen original harness. Reproduce the 24-case matrix per source variant under the original caps. This tests whether the previously failing case becomes initially correct under the same declared fixture.
2. **Source-schema fixture:** derive the six relevant CREATE TABLE statements from `database/schema/mariadb-schema.sql` in the handed-off revision, preserving column widths, indexes, collations and foreign keys. Apply relevant migration deltas deliberately and record them. Seed required parent tables with fixture data only.
3. **Deployed-schema fidelity:** obtain sanitized DDL-only evidence from the designated owner if needed, then compare columns/indexes/constraints with profile 2. Until parity is established, call profile 2 source-schema, not deployed-schema. No production rows or credentials are required.

The available source schema includes a `collections_dateadded_idx` duplicate of `ix_collection_dateadded`, while the October 11 migration drops the former. Apply and record that migration for both source variants if it is part of the selected schema state. Do not silently remove indexes or claim the raw schema dump already incorporates every migration.

Import into a uniquely named disposable database only. Keep foreign-key constraints enabled for the measured workload; temporary disablement for a controlled fixture reset must end before seeding and checks.

## Plan and mechanism gates before timing

The draft runner now enforces these plan checks before any timed observation: baseline and candidate each execute 16 actual-handler cases (fresh/analyzed statistics × dense/sparse IDs × counts 1, 2, 32, 500). It imports the six relevant tables from the frozen baseline's source-schema dump, applies the same obsolete date-index removal for both variants, and preserves effective DDL plus input hash. This profile is the source dump plus that explicit delta; parity with all other migrations or production remains unverified.

The driver captures the locking SQL and bindings emitted by `CollectionHandler::refreshAggregates`, verifies aggregate results, and archives JSON EXPLAIN and transactional `ANALYZE FORMAT=JSON` of that same query. [MariaDB ANALYZE](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/analyze-format-json) executes the query; it is confined to the disposable fixture and rolled back. This gives executed-query measurements under an uncontended diagnostic schedule, not the blocked query's live plan or proof of every physical lock acquired.

Candidate cases must choose PRIMARY with bounded equality/range access and reported row/loop processing no greater than the requested set. A single-row const plan may omit runtime counters; that absence is recorded explicitly and accepted only with its exact primary lookup and independently checked one-row result. Counters are not invented. Baseline plans are preserved regardless of access choice. Missing coverage, absent required executed plans, unexpected access scope, or failed handler results stops the runner before performance work. These gates are prepared and syntax-checked, but have not executed yet.

Use the real collection lock query and retain source/schema/runtime identity. Test parent counts 1, 2, 32 and the configured maximum SQL batch, with dense and sparse existing IDs, aligned/opposite hash mapping, fresh versus analyzed statistics, and a duplicate insert producing a tentative out-of-set primary record.

For every diagnostic case:

- Record concrete synthetic IDs, connection/transaction IDs, statement outcome and observed secondary wait before advancing the schedule.
- Capture preflight EXPLAIN before worker transactions. Capture the executing query's plan where supported through a separate observer (for example SHOW EXPLAIN for the known blocked connection); label unavailable executing plans explicitly.
- Record the actual chosen access key/type. A PRIMARY hint does not prove range access for every shape. Equality, range and full-index scans have different locking footprints.
- Record both client outcomes, error-time raw/decoded lock edges, survivor completion, transaction cleanup and independent committed invariants.
- Include missing-parent, deletion, rollback, replay, duplicate and SQLite compatibility results from the real-handler tests.

These synchronized diagnostic cases establish mechanism. Do not report their elapsed time as natural throughput. The source-schema/deployed-schema distinction must remain in every result.

## Paired historical matrix

Use the original deterministic generator: 10,240 unique parts, 1,024 seeded, 9,216 intended new parts, 12,288 attempted headers, two crosspost groups, and four binaries per collection. Collection shapes: 2 and 32. Writers: 4, 6 and 12. Header batches: 100 and 500; SQL batch: 500.

Run two repeat blocks for screening, with paired baseline/candidate restores for each of the 12 configurations. Randomize baseline/candidate order using a fixed recorded seed; reverse configuration traversal in the second block. This is 24 cases per variant, 48 total observations, not 24 independent performance pairs per configuration.

Restore identical starting data before every observation. Use the same PHP runtime/vendor bundle, normalization stub, private network, storage, durability/isolation and MariaDB version for both variants. Initial matched caps remain app 0.5 CPU/512 MiB, DB 0.75 CPU/512 MiB, pool 128 MiB and redo 64 MiB; match the original flush/binlog settings and record them. CPU relief is a separate experiment.

## Resource measurements without changing the timed workload

Record each service's cgroup CPU usage and throttling counters immediately before the synchronized start and after all children join, alongside memory and the original MariaDB lock/deadlock counter deltas. A monitor driven by the orchestrator can collect the database service's counters without making database queries during the timed interval. Record unavailable counter paths/version differences rather than supplying zeros.

Preserve barrier-to-all-joins wall time, full-service batch latency including retries, successful new unique parts, attempted work, queries, retries/reasons, unique failed IDs, process exits/signals and all initial correctness gates. Capture CPU counter intervals separately from setup/seed/replay/cleanup. Statement/batch samples within one observation are not independent runs.

## Correctness, comparisons and next budget

Candidate acceptance requires zero initial correctness failures and zero exhausted chunks in its declared matrix, plus successful replay/first-write/crosspost/rollback checks. Initial failure remains failure even if replay heals it.

Baseline failures are expected evidence in the previously problematic stress shape. Preserve them and compare reliability outcomes; do not manufacture a throughput ratio using nominal intended work or discard their existence. A pair with a failed baseline or candidate is ineligible for a successful-throughput effect estimate. Report its committed work and failure separately.

Use the screening matrix to choose representative contrasts, including hot 2-collection/4-writer/500-header throughput and 32-collection/12-writer/500-header reliability. Only contrasts with initially valid observations on both sides advance to paired throughput estimates. Collect at least six independent pairs as an initial budget, then evaluate the declared confidence-interval method and useful-effect/regression policy from the [procedure](ingestion-benchmark-procedure.md). Six is not a precision guarantee. A reliability fix may be worthwhile without a speedup; zero observed failures does not imply zero failure probability.

## Artifacts and stop rules

Preserve separate source/schema/protocol/runtime manifests, ordered diagnostic events, plans, raw/decoded lock snapshots, initial/post-replay correctness, interval resource deltas, raw observations, pair/order identity and comparison decisions. Use a new protocol hash when adding instrumentation; the historical frozen protocol remains unchanged.

Stop on failed isolation/provenance, unexpected source differences, harness timeouts, unclassified SQL errors, missing observer barriers or any candidate correctness failure. Retain evidence and return it to the source owner. Never change the hint, arithmetic, retry budget or resource caps mid-matrix.

Remove only owned fixture resources after evidence collection. Deployment, commits, pushes and production configuration changes are outside this execution protocol.
