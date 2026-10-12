# Hot-workload nonregression resolved

The fixed-sample isolated study completed October 11, 2026, 20:05:17–20:17:46 America/Chicago. [Evidence summary](performance/evidence/2026-10-11-hot-nonregression.json) records the design, effects, hashes and scope.

Durable [raw study artifacts](performance/evidence/raw/2026-10-11-fixed-study.tar.gz) retain all epochs, worker outcomes, plans and logs. The [complete report supplement](performance/evidence/raw/2026-10-11-fixed-study-reports.json.gz) includes the final report, handoff, randomized order, analysis and topology supplement. The [publication manifest](performance/evidence/raw/2026-10-11-publication-manifest.json) records original and published hashes. Only machine-specific absolute path strings were replaced; measured numbers, failures and observation order are unchanged. Archived scripts are provenance copies, not supported repository commands.

The [exact final report](performance/evidence/raw/2026-10-11-fixed-study-exact-report.json.gz), after gzip decompression, has the advertised SHA-256 `338c98ff2a355d33e3b7dc995827ac62d27c51bc56fc47a930e6c76a4599a66c`. This preserves the actual analyzed bytes separately from the equivalent parsed report supplement. The [tested source patch](performance/evidence/raw/2026-10-11-collection-primary-source.patch) reconstructs the candidate from source revision `54c140d7dc4c2500bf69115047b3aabfac6942f0`; the candidate CollectionHandler is also published in [squash commit 6a2555d57](https://github.com/xbmc4lyfe/newznab-tmux/blob/6a2555d57cca071f64d3d5315e3da82fde40c04f/app/Services/Binaries/CollectionHandler.php) with SHA-256 `7d58bf1cd097be7c22a3ecaec175b35166bd4ee7109dab76d1117f4b52768474`.

The PRIMARY-access candidate passes the declared 10% hot-workload nonregression budget in this controlled profile. No additional source change, unhinted fallback, weakened lock or runtime tuning was needed.

| Primary hot-workload metric | Candidate/baseline effect | 95% interval | Gate |
|---|---:|---:|---|
| Successful new-part throughput | 1.0018 | 0.9864–1.0174 | Lower bound ≥ 0.90: pass |
| Full-service batch p95 latency | 0.9897 | 0.9680–1.0119 | Upper bound ≤ 1.10: pass |

## Design and recorded checks

The archived epoch checks prove their recorded parts, aggregates, readiness and total cross-post row counts. They do not prove exact `(collections_id, group_name)` membership or replay-only membership/timestamp updates: canonical replay supplies no new membership and its verifier ignores `last_seen_at`. Treat the stored `correctness.passed` and `replay_passed` flags within that scope; they are not complete metadata-correctness claims. A supplemental semantic check is distinct from these immutable timing observations and cannot retroactively expand their checks.

The separate [metadata semantic supplement](performance/evidence/2026-10-11-metadata-semantic-supplement.json) passes for the frozen baseline and candidate with the real HeaderStorageService in isolated Docker PHP 8.5.11 / MariaDB 11.4.8. Its two-collection, eight-binary, sixteen-part fixture asserts complete normalized collection/group pair sets, then replays the same articles with a fresh third group and a controlled two-minute clock advance. Both collections gain exactly the new membership and advance `last_seen_at`; parts and their identities/bytes, collection identities, aggregate counts/sums, readiness and `dateadded` remain unchanged, with no failed article IDs or orphans. [Raw fixture, schema and output](performance/evidence/raw/2026-10-11-metadata-semantic-supplement.tar.gz) and the publication manifest retain provenance. This is focused semantic evidence, not another timing observation or a claim that all old epochs exercised these cases.

The supplement's [exact application source bundle](performance/evidence/raw/2026-10-11-metadata-semantic-source.tar.gz) is committed with SHA-256 `26c399a83019bf73cb3177e77a9fce0a4c6f0d10bc659784c0f7072ebb00db59`. All 2,563 tracked files match the recorded application revision `a282ca24990fb11c586cb9544ae4bf2833e73c18` (Git tree `b86e26bad77203cf07be3cc08eb2b9b02879b100`). Only the CollectionHandler overlay varies between its baseline and candidate runs. The bundle contains published repository source, not production configuration or vendor dependencies; it is provenance material, not an installed runtime.

- Fixed 20 independent hot paired blocks, eight independently restored 10,240-part fixture epochs per source arm; four spread paired blocks with four epochs per arm as a secondary safeguard.
- One warmup per arm retained separately: 352 measured epochs plus 48 warmups, all 400 passed the recorded initial storage checks. Canonical replay, conflicting first-write checks and normal worker exits also passed throughout.
- Source order counterbalanced 10/10 for hot blocks and 2/2 for spread blocks with a fixed randomized schedule. No interim budget evaluations, optional stopping, outlier deletion or treating nested epochs as independent samples.
- Primary effects are matched epoch-log throughput and p95 ratios averaged within each block; two-sided Student-t intervals use the 20 outer hot blocks. Aggregate work/time and pooled batch p95 are secondary diagnostics, not replacements for the preregistered primary metrics.
- App and database used distinct physical cores through logical CPUs 11 and 10, without CFS quota throttling; memory stayed 512 MiB each and buffer pool 128 MiB. Source, runtime, schema, input, retry and correctness identities remained frozen.

## What explains the earlier 12% estimate

The original two hot pairs both ran candidate first and used short quota-throttled observations with substantial between-run CPU drift. The balanced 40-observation follow-up did not reproduce that point penalty, but its interval was too wide. The separate pinned follow-up also remained imprecise. This longer, fixed grouped study resolved the budget without changing the candidate.

The actual hot-table baseline and candidate both choose PRIMARY range access. The changed locking SELECT is not a consistent dominant timing cost. Passive diagnostics found no garbage-collector runs during the 320 measured hot epochs. Indexed second-resolution timestamp changes correlated with only small median differences here; they do not establish the cause of earlier outliers. The exact cause of each old slow observation remains unproved. The evidence supports sampling/order/runtime variation rather than a persistent 12% source-code penalty; it does not support inventing a narrower locking fallback.

## Deadlock gains retained

Across measured epochs, baseline deadlocks totaled 147 and candidate deadlocks zero, with all candidate epochs passing the recorded storage checks. The spread safeguard's geometric throughput ratio was about 2.05 and p95 ratio about 0.146. It is secondary evidence with four outer blocks, not a general production speed claim.

Cumulative row-lock waiting still rose from 173,072 ms to 197,071 ms. Successful serialization and failed deadlock attempts have different costs. Do not describe the result as lower total waiting or universal deadlock elimination.

The study uses synthetic cache-fit data, tmpfs, PHP NTS/x86 and a pinned resource profile. It establishes the declared experimental budget and preserves the reproduced correctness gains; it does not prove production capacity, real-storage performance or deployed-schema parity. Source review, merge, deployment and live verification remain the source/deployment owners' separate gates.
