# Ingestion benchmark and experiment procedure

Historical investigation procedure: the pending-stage statements below describe the initial planning checkpoint. The subsequent [screen](collection-primary-screen-results.md) and [fixed-sample validation](hot-workload-validation.md) record completed execution and its limits; use those results when deciding what remains to validate.

This procedure records the October 11 research and provides acceptance rules for future isolated experiments. It is an experimental procedure, not a standing instruction to change production concurrency or database settings.

Read the [detailed investigation](ingestion-contention-investigation.md) for decoded records and the later reduced SQL causal probe. It narrows the next candidate to collection-lock query access before broad parent ownership. Its unhinted preflight plan is a secondary-index scan/filesort, not `ALL`; its exact original full-service plan remains unavailable.

## Baseline evidence

- [Raw 24-case report](performance/evidence/2026-10-11-ingestion-baseline.json): SHA-256 `e44b05066c1179f6f977b81ef65db21ebc736b42cbb5c47bcc64d860dd099959`.
- [Runtime and resource metadata](performance/evidence/2026-10-11-ingestion-environment.json).
- Frozen PHP protocol SHA-256: `34ca200a7285522c265ed3d6170b103c2bc944e20a9880d9fe15893605312717`.
- Four real storage class hashes appear in the report. The temporary original harness is retained locally under the `newznab-hotpath-bench-current` pointer, with `bench.php`, `schema.sql`, and `remote_run.py`. It has not been promoted into a supported repository tool; port it through the plan's harness task before treating execution commands as reusable.
- Completed off-host matrix: October 11, 2026, 17:34:02–17:35:21 America/Chicago. Twenty-two cases passed initial correctness; two retry-exhaustion cases remain invalid even though subsequent replay healed them.
- A preceding local partial attempt at 17:16:54.502–17:17:54.645 was stopped and excluded from the authoritative report. Its shared-host load is a confound for production observations during that interval.

Each case used 10,240 unique parts, 12,288 attempted headers, 1,024 seeded parts, and 9,216 intended new parts. Correct final bytes were 11,129,000. Shapes had 2 or 32 collections, 4 binaries per collection, and 2 crosspost memberships per collection. Writer counts were 4, 6, and 12; header batches 100 and 500; SQL batches 500; there were two opposite-order repeats.

The 32-collection/12-writer/500-header cases stored 8,554 and 8,893 new parts initially, below 9,216. They reported 985 and 494 distinct failed article IDs. Failed-ID counts differ from the unique-part deficit because attempted articles include duplicate work. Do not classify returned failures as silent loss, or replay recovery as initial success.

## Tool selection

Use real `HeaderStorageService` and schema for application acceptance. [Sysbench](https://github.com/akopytov/sysbench) custom Lua can isolate a precisely reproduced SQL transaction and explicit shared-key distribution; generic OLTP scores do not validate ingestion semantics. Select its random distribution and seed explicitly, and use its warmup/rate/interval controls for a declared experiment.

Use [PHPBench](https://phpbench.readthedocs.io/en/latest/annotributes.html) for normalization, grouping, deduplication, or statement construction independent of database concurrency. Specify iterations, revolutions, and warmup. Do not use its deviant-sample replacement as a contention filter.

[MariaDB TAF](https://github.com/MariaDB/TAF) is an optional lifecycle/configuration provenance framework. Adopt it only if repeated database-version or configuration matrices justify a new tool. No new package is required for the initial lock-cycle reproduction.

## Isolation and run preparation

1. Use a separate host. Resource limits on a production host do not eliminate shared CPU, I/O, and memory interference.
2. Create a uniquely named disposable Compose project and internal network, with no published database ports, production bind mounts, production credentials, or production database endpoint. Identify the scratch database unmistakably.
3. Record immutable source revision/class hashes, protocol hash, runtime image IDs, PHP version/ZTS/architecture, MariaDB version, schema/index/collation, isolation, durability/binlog settings, CPU/memory caps, storage type, buffer pool, redo/log buffer, and host background load.
4. Match candidate and baseline environment. Label deliberate differences. For a production-relevance stage, match PHP mode/architecture and database durability as closely as practical.
5. Start from identical restored fixture state per run, clear stale synchronization/output files, create independent worker connections, and release all workers through an explicit barrier. Measure wall time until every worker joins, including retries.
6. Predeclare setup, worker, run, and matrix timeouts. Record normal exit codes and signals. A timeout, skip, crashed child, or failed setup is an invalid observation.
7. Freeze the protocol before a comparison. Changes create a new protocol version and matrix; do not change mid-run.

## Correctness contract

Validate initial committed state independently of the service's counters: exact unique parts and bytes; binary current/total parts and sizes; collection size and readiness; crosspost membership; no orphans; explicit distinct failed articles and retry reasons.

Run canonical replay separately. It must preserve counts and sizes, and repair behavior must remain defined. Attempt conflicting duplicate sizes and message IDs and verify first-write-wins. Verify newly returned rows remain provisional until successful transaction commit. Include rollback and concurrent-winner scenarios.

Never give an invalid initial case nominal successful throughput. Report actual committed unique work separately and keep failed cases in the reliability summary. Reject a configuration from performance ranking if any paired run fails correctness. Also report invalidity frequency rather than presenting only surviving pairs.

## Metrics and experiment order

Start with a minimal mechanism test before a throughput matrix. The coordinating optimization session independently proposed this two-stage reduction:

- **Stage A:** one disposable cached MariaDB 11.4.8 container, `--network none`, no published ports or production mounts, 1 CPU, 512 MiB memory, and a 64–128 MiB pool. Drive two bundled MariaDB clients through the container's own Unix socket. Start with 32 parents using aligned ID/hash order; opposite ordering and neighboring keys are separate controls. Port to the exact schema with its primary/unique indexes and foreign keys. Use captured SQL phases and explicit barriers/observed lock waits. Expand the fixture if it cannot reproduce the observed cycle; do not substitute an unrelated artificial deadlock.
- **Stage B:** reuse the native PHP bundle in a private two-service network; begin with 2/4 writers and at most 1,000 parts. Repeat paired baseline/candidate checks, preserving all correctness gates and CPU throttling measurements. These are screening tests, not capacity measurements. Advance to the larger matrix only after the candidate passes.

Stage A changes the isolation topology deliberately; it is a causal test, not a matched timing comparison with the historical two-container run. Cached images reduce setup work but do not waive source/runtime provenance. A reduced Stage A probe has since completed with three controls; see the investigation and archived report. Exact-schema service validation and Stage B remain pending.

Record attempted headers, successfully committed new unique parts, wall duration, query count/duration, p50/p95/p99/max service batch latency, attempts/retry reasons, exhausted chunks, distinct failed IDs, all correctness gates, memory, and [cgroup CPU usage/throttling](https://cdn.kernel.org/doc/html/latest/admin-guide/cgroup-v2.html). Do not label full-service batch time as SQL transaction latency.

Use interval deltas of MariaDB counters. `Innodb_row_lock_time` is cumulative milliseconds; `Innodb_row_lock_waits` counts waits. Record deadlocks separately, and normalize costs by successful work. [`Innodb_log_waits`](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/system-variables/innodb-status-variables) indicates log-buffer-induced flush waits, not directly insufficient redo-file capacity. Missing version-specific counters are unavailable, not zero.

Run these experiments in order:

1. **Causal lock query:** port the reduced cycle to the exact schema and real handler, retaining the aligned seed and outside-predicate tentative record. Compare the unhinted query with verified PRIMARY range/equality access. Opposite order and neighboring keys are additional controls. Defer parent ownership until the narrower candidate is understood.
2. **CPU versus contention:** hold data/storage fixed; vary app and DB CPU allowances independently at 4/6/12 writers. Compare throttling, successful work, lock waits, and retry tails.
3. **Ownership and duplicates:** compare disjoint parents, shared uniform parents, and explicit hot-key skew. Independently vary duplicate frequency through 0%, 20%, and replay-heavy input while maintaining equal intended new work.
4. **Storage pressure:** grow the active data and index working set beyond the pool and sustain writes long enough to observe checkpoint/flush behavior. Added cold rows alone do not establish pressure. Measure physical/logical reads, free-page waits, dirty pages, checkpoint age, and storage latency using [MariaDB guidance](https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-buffer-pool).
5. **One knob at a time:** change only a parameter associated with the demonstrated pressure. Relaxed durability is a different operating contract and must be reported separately.

## Comparison and acceptance procedure

Use randomized paired baseline/candidate runs in the same host/time block, restoring identical data each time. Declare warmup and measurement windows before running; keep startup/cold-cache results separate. Initially collect at least six independent pairs for a proposed change, then evaluate uncertainty; six pairs alone are not sufficient evidence. [Randomized blocking](https://www.itl.nist.gov/div898/handbook/pri/section3/pri332.htm) controls nuisance variation, and [performance confidence-interval research](https://www.cs.kent.ac.uk/pubs/2012/3233/) explains uncertainty in ratios.

Proposed decision policy, not a claim from those references: accept a performance candidate only with zero correctness failures, zero exhausted chunks in the declared acceptance matrix, and a 95% paired-effect confidence interval whose lower bound exceeds a predeclared useful improvement. Default useful throughput improvement is 5%; the upper confidence bound for p95 batch-latency regression must stay below 10%. These are project decision thresholds, not universal constants. If the interval overlaps the threshold, collect more independent pairs or report inconclusive. Do not discard outliers merely because the candidate looks worse.

For a reliability-focused lock fix, first require the controlled cycle to disappear and every correctness gate to pass; require evidence that throughput and p95 regression stay within the same 10% budget rather than demanding a speedup. Keep maximum-throughput closed-loop tests separate from fixed-arrival-rate latency tests.

## Verification, cleanup, and sharing

For future PHP changes, run affected PHPUnit tests, Pint until clean, PHPStan, and changed-file syntax checks using the execution precedence in AGENTS.md. This investigation has explicit user authorization for Docker PHP validation; unrelated work retains the host default unless separately authorized for container execution. Record exact commands, versions, and results in that implementation's report; this documentation-only change runs no PHP validation.

Archive fixture-only reports, metadata, protocol/source hashes, failure snapshots, and comparison summaries. Remove only the run's own containers, network, and scratch after collecting evidence. Do not prune shared Docker resources.

Share measured results, limitations, and candidate gates with ingestion owners; share counter interpretation and benchmark-load intervals with monitoring sessions; share duplicate-warning/transaction semantics with import owners. Keep proposed changes separate from authorization to execute or deploy them.
