# Verify downloads before publishing releases

Status: approved October 11, 2026. Review and rollout are tracked in fork PR #53.

## Recovered request and decisions

Continue Claude session `Release identification percentage`, CLI session
`b7770239-669b-4c57-bfbf-e73b9d668c55`. Its admin group-count fix, fork PR #50,
is merged as `3434fbe6a` and present in this checkout.

The remaining requirement is to publish a release only when it is complete or
PAR2 can repair it, independently of size. The previous session selected fetching
PAR2 metadata, waiting 48 hours for missing articles, and rechecking existing
releases. On October 11 the user clarified: **download and verify before
publishing**. Headers and advertised recovery-volume names are insufficient proof.

The unused alternate-article-server changes remain staged in their original
worktree. Preserve them. Verification uses the configured primary provider and,
when enabled, the configured alternate provider with the same bounded BODY limit.

## Current evidence

At database time 2026-10-11 15:30:26, information_schema estimated 26,512,523
collections, 28,283,318 binaries and 127,331,818 parts. These are estimates, not
exact counts. Allocated data plus indexes were about 54.8 GiB across those tables.
The live ingestion settings remain 2-hour delay, 48-hour timeout and 72-hour part
retention. Do not multiply hot-table retention to implement the waiting period.

`ReleaseProcessingService::reconcileCollectionAggregates()` still treats stale
collections as ready and overwrites their declared file count with the observed
count. `CollectionHandler::refreshAggregates()` has a separate ready path. Both
must feed verification rather than authorize publication. The final guard belongs
in `ReleaseCreationService`, so neither route can bypass it.

`NNTPService::getMessages()` accumulates decoded bodies in one PHP string and
returns early near 1.7 GB. It cannot verify a 50 GB payload safely. The production
Dockerfile does not currently install a PAR2 verification executable.

## Approach and alternatives

Recommended: durable candidate manifests, a separate bounded download verifier,
and an atomic publication gate. Retain the existing release tables for published
releases. Store pending candidate records in dedicated tables and compressed
manifests under the existing bind-mounted storage tree.

Holding all collections, binaries and parts for 48 hours preserves the current
ingestion representation but expands an already large database. Publishing first
and verifying later allows incomplete NZBs into search and contradicts the request.
Neither is the proposed approach.

## Candidate lifecycle

1. Record a candidate's declared file count, collection identity, file subjects,
   groups, message IDs, part numbers and article sizes without rewriting expected
   counts. Staleness triggers assessment, never a successful verdict.
2. Snapshot touched candidates in bounded pages to an immutable manifest. Commit
   the manifest reference and version durably before deleting hot-table rows.
   A failed handoff leaves the original rows available for retry.
   Failed individual handoffs do not abort subsequent collection windows. Stale
   cleanup uses a bounded, locked cursor and excludes failed handoffs from deletion.
3. Retain a compact identity record so late articles from other groups attach to
   the same pending candidate. Merge manifests by file and segment identity;
   conflicting bytes, names or message IDs require explicit resolution. A
   collection hash or matching file size alone does not prove upload identity.
4. Claim verification work with a lease and token. Download outside database
   transactions. Persist checkpoints and renew leases so crashes can resume.
5. Publish only a successful result for the current manifest version, using an
   atomic claim and idempotency key. An older result cannot publish a newer or
   conflicting manifest. Build the published NZB from that exact verified version.
6. Retry missing articles and transient server failures. Unknown, malformed,
   capacity-limited and unrepairable candidates remain unpublished. Expire waiting
   candidates after 48 hours without new unique articles, matching the previous
   decision. Repeated duplicate headers do not reset the clock.

Configured site/group size and file-count limits still apply before publication,
using decoded verified bytes and the full verified inventory, including recovered
sources absent from headers. Subject extensions and advertised counts cannot
justify early rejection: an article labeled `masked.rar` can contain PAR2 data
that reconstructs otherwise unseen files. A successful payload that violates
publication policy becomes terminally filtered and releases its reserved capacity.

With verification required, the legacy NZB queue and direct header-only writer
remain disabled, including pre-existing `NZB_NONE` rows. Verified publication
writes its exact NZB directly. Native managed installations provision PAR2 and
check executable availability before activating processing services.

Archive-and-merge handoff must coordinate with ingest transactions and existing
cleanup. The collection timeout and 72-hour cleanup cannot erase a candidate
before durable transfer. Only touched candidates enter the queue; do not scan
every historical part each cycle. Backfill can update an existing pending record.

## Download and verification contract

Use message-ID BODY requests through the configured primary proxy. Stream decoded
articles to controlled temporary files, preserving yEnc offsets and validating
available part/file checksums and declared lengths. Never concatenate an entire
release in PHP memory, execute downloaded content, or use untrusted filenames as
filesystem paths. Network failures and insufficient scratch space are retryable
unknown results, not evidence of a bad release.

For releases without PAR2, require all declared files and segments, coherent file
identities, contiguous decoded coverage and successful integrity checks available
in the upload. Missing or ambiguous file-count information cannot establish
completeness. This proves the indexed upload's declared content at verification
time; it cannot prove an uploader supplied an authentic movie or archive.

For PAR2 sets, download and validate their metadata, source payload and sufficient
recovery packets. Match protected files using validated identity information;
ambiguous same-size files are not interchangeable. Check each recovery set
independently. Do not sum advertised `volNN+MM` values: packets may be duplicated,
corrupt or from another set. Deduplicate recovery slices by set and exponent.

Use an established PAR2 implementation for verification and repair. When source
data is damaged, repair in scratch storage and verify the repaired protected
files against the PAR2 hashes. Only successful final verification establishes
repairability. This introduces a proposed runtime system dependency that must be
approved with this design; no Composer or npm dependency changes are proposed.

PAR2 proves only its protected file set. Every required unprotected payload file
must also pass the ordinary integrity/coverage checks. Optional auxiliary files
must be explicitly identified; filename extension alone cannot make a missing
required payload optional. Unsupported encodings or incomplete metadata stay
unknown rather than passing.

The published NZB must contain the verified available source segments and recovery
segments needed to reproduce the repair. Repaired scratch bytes are not Usenet
articles and cannot replace absent message IDs. Verification is time-stamped;
provider retention can change availability after publication.

## Capacity and persistence

Start with one verifier and at most four article connections. Make worker count,
connection budget, bandwidth budget, maximum scratch allocation and free-space
reserve configurable and document every env setting in both env examples.
Reserve enough scratch space for the candidate and its repair output before work
starts. A candidate larger than the configured budget waits for capacity; size
alone never determines completeness or causes deletion.

Persist manifests, verdicts, checkpoints and leases on bind-mounted storage.
Use bounded queues, per-group fairness and delayed retries; prioritize new
candidates over the existing-release audit. When durable pending storage is full,
pause affected ingestion before advancing its scan position. Do not silently drop
headers or convert overflow into published releases. Expose backlog age, verified
bytes/second, scratch usage, verdicts and capacity stalls to existing monitoring.

Measure throughput before enabling the live publication gate. Full verification
requires reading payload bytes, so indexing throughput can exceed verification
capacity. Increasing database retention cannot solve that mismatch.

## Existing releases and rollout

Use the same verifier for a resumable audit of existing NZBs, with dry-run reports,
bounded ID ranges, a persisted cursor and verdict reasons. Preserve unknown
results for retry. Previously published incomplete NZBs may lack whole-file
metadata, so their missing content must not be guessed from observed counts.

The prior user selected rechecking and deleting bad releases. First produce a
reviewable dry-run report. Actual deletion must use
`App\Services\Releases\ReleaseManagementService`, removing associated NZBs, images
and search entries; outage failures never qualify for deletion. Existing published
releases remain visible during the initial audit: enforcing verification for that
backlog requires a separate migration decision, not an implicit bulk hide.

Roll out in three stages: shadow verification and throughput measurement; enable
the gate for new candidates; audit and clean existing releases. This feature
session stages changes for maintainer review. The designated deployment session
owns any image rebuild, stack restart or live enablement.

## Acceptance and validation

- Complete small and large payloads publish once; incomplete unrepairable payloads
  remain unpublished, including after the old two-hour delay.
- Missing segments and entire files that PAR2 can actually reconstruct pass only
  after repair and final integrity verification.
- Duplicate recovery packets, mixed sets, bad checksums, ambiguous filenames,
  unsupported encodings and absent required unprotected files cannot pass.
- Late cross-group arrivals merge before verification; manifest changes invalidate
  stale results. Ingest, cleanup and verifier races cannot lose articles or publish
  two releases.
- Worker termination resumes safely. Network outage, disk exhaustion and queue
  saturation produce pending/unknown outcomes without advancing lost scan ranges.
- Existing-release dry run performs no deletion; destructive audit removes every
  associated artifact through the established cleanup service.
- Use mocked NNTP in PHPUnit and real local PAR2 fixtures. Add integration cases
  for MariaDB claim/handoff races and measured streaming memory limits. Run the
  affected tests, Pint, PHPStan and PHP lint before staging implementation.

## Source references

- Original transcript:
  CLI session `b7770239-669b-4c57-bfbf-e73b9d668c55`.
- [Merged group-count PR #50](https://github.com/xbmc4lyfe/newznab-tmux/pull/50).
- [PAR2 format specification](https://parchive.sourceforge.net/docs/specifications/parity-volume-spec/article-spec.html):
  packet integrity, recovery-set identity, protected files and recovery slices.

Implementation and validation evidence: `2026-10-11-repairable-releases-plan.md`. The designated deployment session owns the live rollout.
