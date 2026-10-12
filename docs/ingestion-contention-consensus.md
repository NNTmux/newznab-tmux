# Ingestion contention consensus

Consultation requested by the user on October 11, 2026. This records explicit session responses, agreed scope, and remaining conditions. Agreement concerns the next investigation and validation sequence; it does not establish that the candidate is correct or authorize a production rollout.

## Session positions

| Session | Explicit position | Scope and qualifications |
|---|---|---|
| NEWZNAB OPTIMIZATION | Approve with conditions | CollectionHandler-only PRIMARY-access candidate; real-handler red/green regression, actual-schema plan/correctness gates; source/test owner |
| Reduce the rename backlog | Approve with conditions | Same narrow investigation; no Binaries overlap with its PR #59 work; no deployment ownership |
| NEWZNAB - Monitor | Approve with conditions | Next experiment sequence only, not source-fix or rollout approval; independent correctness and plan evidence required |
| NEWZZNAB PREDB | Approve with conditions | Next experiment only; source-frozen real-handler red/green, exact-schema/runtime and executed-access evidence; no ingestion source conflict |
| NEWZNAB OPTIMIZATION2 | Approve with conditions | Explicit recommendation after direct human approval; isolated CollectionHandler experiment, full validation gates and read-only production monitoring |

All five consulted sessions explicitly approve the narrowed isolated experiment sequence with conditions. No session reports a technical objection to that sequence or an ownership conflict. This is consensus on investigation and validation scope, not proof of a completed fix or approval for production rollout.

## Agreed direction

1. Port the reduced physical wait graph to an actual CollectionHandler regression. Require an observed competing unique-secondary wait before issuing the collection-lock query.
2. Demonstrate the unhinted baseline's 1213/matched cycle, then test explicit PRIMARY access while preserving transactions, upserts, COUNT/SUM, CASE updates, retry budget, lifecycle guards, and SQLite routing.
3. Limit the initial source candidate to CollectionHandler. BinaryHandler requires its own plan/cycle/regression evidence before applying an analogous change.
4. Keep broad parent ownership, retry-policy changes, RETURNING arithmetic, and database tuning as separate experiments.
5. Separate barrier-controlled mechanism tests from natural-schedule throughput comparisons and CPU-throttling measurements.

## Required gates

- Use exact source/runtime/schema provenance. The coordinating session's drafted regression uses a reduced causal schema with child foreign keys; that is not yet the entire deployed schema. Record this distinction until the full-schema gate is met.
- Validate both clients' explicit statement and transaction outcomes, not just marker receipt or process exit zero.
- Independently verify stored parts, count/sum, readiness, crossposts, first-write duplicates, replay, rollback, missing-parent and deletion races.
- Capture and examine actual plan/access evidence across small, dense, sparse, and maximum bounded ID sets and varying statistics. A hint or ORDER BY is not a universal physical-lock-order guarantee.
- Run affected tests, Pint, PHPStan, and changed-file syntax checks for implementation changes, following AGENTS.md execution precedence. Docker PHP validation is explicitly authorized for this investigation.
- Before a performance acceptance claim, run source-matched paired real-service comparisons; retain every initial correctness failure and exhausted chunk; record CPU throttling and compare successful unique work.
- Preserve the historical-plan absence, simplified control/single-observation limits, and victim-label/client-outcome discrepancy. Do not assign the cycle's cause using the victim number alone.

## Ownership and coordination

| Work | Owner | Coordination rule |
|---|---|---|
| Source candidate and regression | NEWZNAB OPTIMIZATION | Freeze unhinted baseline before red evidence; one owner edits ingestion source |
| Private devruntime preparation and baseline red validation | NEWZNAB OPTIMIZATION's designated agent | Coordinating owner reassigned this execution to unblock it; PERF holds transfer/runtime work and has no active preparation process |
| Candidate paired benchmark | NEWZNAB PERF OPTIMIZATION after explicit green-source handoff | One executor; fixed source/protocol transfer; avoid duplicate runs and local production-host load |
| PR #59 cleanup/review | Reduce the rename backlog | Separate source scope; no ingestion edits or rollout |
| Production observation | Monitoring sessions | Preserve read-only behavior and maintenance cohorts; health/progress is not ingestion correctness proof |
| Deployment | Existing single deploying owner | Consultation does not request a deploy or bypass current-head review and verification |

## Review corrections incorporated

OPTIMIZATION2 later identified a concrete P2 fixture issue: the parent Laravel connection inherited URL/socket/port configuration while the child PDO used only the test host and default port. PERF independently confirmed the mismatch in the frozen test copy. The source owner reports fixing it before database creation by clearing URL/socket and setting the same explicit port 3306 for parent and child. The refreshed fixture hash is `2a0f03ea2f4a5c000751f5671ad0005b3d0b6c8b80f5970832f7a9d0dfde569d`; the CollectionHandler candidate hash remains `7d58bf1cd097be7c22a3ecaec175b35166bd4ee7109dab76d1117f4b52768474`. Fresh regression and broader checks remain the validation executor's responsibility; this correction is not a passing-suite claim.

NEWZNAB - Monitor identified two inconsistencies, both corrected:

- The procedure now makes aligned ID/hash order canonical; opposite ordering is a separate control.
- The plan now specifies ten total attempts and nine backoffs, rather than ten retries.

The rename owner supplied newer backlog counters but explicitly separated them from ingestion-lock causality. Live monitor health/progress observations are likewise availability evidence only; they do not replace fixture correctness or source-matched acceptance.

PreDB agrees with the validation gates and requests executed-access evidence, not merely a preflight plan. Its staged PAR2/NNTP/name-matching, release-file metadata/count, and checkpoint work has not been deployed or migrated live. Those writers are outside the reproduced collectionhash-cycle evidence; any concurrency recommendation for them needs role-specific observations. PreDB's reply is experiment consultation approval, not rollout approval. The private red-validation executor reassignment remains as recorded above.

## References

- [Detailed investigation](ingestion-contention-investigation.md)
- [Experiment procedure](ingestion-benchmark-procedure.md)
- [Candidate benchmark protocol](ingestion-candidate-benchmark-protocol.md)
- [Final hot-workload validation](hot-workload-validation.md)

OPTIMIZATION2's explicit response follows direct human approval of its recommendation. It confirms CollectionHandler-only scope, source-frozen exact-schema real-handler red/green, executed plans, deterministic synchronization, both client transaction outcomes, committed correctness and SQLite compatibility. It preserves the designated owners and read-only production monitoring.

Any new contrary evidence reopens the relevant decision rather than being hidden under the consensus label. Agreement does not waive any outstanding validation gate.
