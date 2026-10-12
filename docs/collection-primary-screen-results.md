# Collection PRIMARY access: paired screen results

This is the historical first screen. The subsequent [fixed-sample validation](hot-workload-validation.md) resolves its hot-workload nonregression concern; retain these initial failures and limitations when interpreting the later result.

Storage and replay pass counts below refer to the archived oracle's recorded checks. As described in the final validation's coverage limits, total cross-post row counts and canonical replay do not establish exact cross-post membership or replay-only metadata updates.

The isolated screen completed on October 11, 2026, 19:03:57–19:07:31 America/Chicago. [Summary evidence](performance/evidence/2026-10-11-collection-primary-screen.json) records hashes, counts and pair-level ratios. Full private raw reports, plans, logs, source manifests and measured protocol are retained in `paired-evidence.tar.gz` under the existing benchmark scratch pointer; its hash is in the summary.

For durable review, the [published raw screen](performance/evidence/raw/2026-10-11-initial-screen.tar.gz) and [complete report supplement](performance/evidence/raw/2026-10-11-initial-screen-reports.json.gz) are committed alongside the [follow-up artifacts](performance/evidence/raw/2026-10-11-followups.tar.gz) and [follow-up reports](performance/evidence/raw/2026-10-11-followups-reports.json.gz). The [publication manifest](performance/evidence/raw/2026-10-11-publication-manifest.json) records original and published hashes and path-only redactions; no observations or failures were removed.

## Completed evidence

- 48 observations: 24 baseline and 24 candidate; only CollectionHandler differs between source bundles.
- 32 mandatory actual-handler source-schema plan checks passed, covering dense/sparse IDs of 1, 2, 32 and 500 with and without explicit ANALYZE. Exact locking bindings, expected obsolete-index removal and cross-source effective DDL parity were verified.
- Real foreign-key deletion-after-prefetch retry and atomic rollback/replay probes passed for both sources.
- Candidate initial correctness: 24/24. Baseline: 23/24. All 48 canonical replay, conflicting first-write and normal child-exit checks passed; CPU counters were available for both services.
- The failed baseline used 32 collections, 12 writers and 500-header batches. Two chunks exhausted ten total attempts with error 1213; only 8,546 of 9,216 intended new parts committed initially. Its full pair is excluded from throughput comparison, and that configuration is not ranked from its remaining single valid pair.
- All owned benchmark containers were absent after completion; database/network cleanup returned zero. Runtime ownership returned to the coordinator for broader validation repair.

## Material tradeoffs

| Workload | Candidate/baseline throughput ratio, geometric mean | Interpretation |
|---|---:|---|
| 32 collections, 4 writers, 500 headers | 1.6525 | About 65% faster in this two-pair screen |
| 32 collections, 6 writers, 500 headers | 2.2244 | About 122% faster in this two-pair screen |
| 2 hot collections, 4 writers, 500 headers | 0.8789 | About 12% slower; unresolved nonregression concern |

Deadlocks fell from 416 to 6 across the matched observations, but cumulative row-lock time **increased** from 115,134 ms to 185,913 ms. Fast failed deadlock attempts and successfully serialized waits are different outcomes. This is evidence of fewer deadlocks and complete candidate storage, not lower total waiting or universal speed improvement. Six candidate deadlocks still occurred and recovered; global deadlock elimination is not established.

The fixed caps were application 0.5 CPU/512 MiB and database 0.75 CPU/512 MiB. CPU throttling, short observations, cache-fit synthetic data, tmpfs storage and two independent pairs per configuration limit inference. These results do not establish production capacity or a real-storage/durability improvement. Source-schema plan tests are not deployed-DDL parity proof.

## Remaining gates

The coordinator's focused real-handler regression and affected tests passed. Its isolated full suite was not passing at measurement time: live-API/no-egress and deploy-process environment failures require separate resolution and, where relevant, matched baseline evidence. Do not label them pre-existing without that comparison.

Before performance acceptance, coordinate further targeted independent pairs for the current four-writer/500-header hot and spread shapes, preserve CPU/resource controls, and evaluate the declared confidence-interval/regression policy. The present screen alone does not satisfy a nonregression claim. No source commit, merge or production deployment follows automatically from these observations.
