# Verified Release Publication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Download and verify every newly indexed release before publishing its NZB.

**Architecture:** Move bounded collection snapshots into durable compressed manifests. A single resumable verifier downloads individual articles, validates yEnc ranges/checksums, and verifies or repairs PAR2 sets in scratch storage. Only a current successful verdict can atomically publish the exact verified manifest.

**Tech Stack:** Installed Laravel 13/PHP 8.5, MariaDB, PHPUnit, existing yEnc decoder, Symfony Process, PAR2 CLI.

**Execution:** Native implementation authorized with design approval and the instruction to keep going.

**Spec:** `docker/docs/2026-10-11-repairable-releases-design.md`

## Global Constraints

- Full payload download precedes publication; file size never grants eligibility.
- Apply configured admission limits to the verified decoded size and inventory before publication; masked headers cannot prove that reconstruction is impossible.
- Keep declared file counts; age alone cannot make a candidate complete.
- One verifier, at most four article connections; start with one connection.
- Retry unknown outcomes; expire after 48 hours without new unique articles.
- Persist pending manifests and scratch under the existing storage bind mount.
- Every new commit is authored and signed as `xbmc4lyfe` and confirmed GitHub Verified. GitHub actions use that account.
- Complete current-head review and checks before squash-merging into fork/master; deploy under the shared guard and prove real verified publication afterward.
- Keep existing-release audits read-only unless a separate deletion scope is authorized.
- Every new env setting appears in both `.env.example` and `docker/config/app.env.example`.
- Stage only project changes. Keep execution logs in ignored scratch storage.

## Review Focus

- An article changes filename, size or range between segments: remain unpublished.
- A worker loses its lease or its manifest changes: reject stale publication.
- Capacity is exhausted or the provider disconnects: retry without declaring failure.
- Different PAR2 sets and duplicate packets: rely on actual repair plus final verification.
- A manifest handoff races with header writes or crashes: keep a durable copy and retry safely.

## Task 1: Runtime and verification contract

**Files:** `config/release_verification.php`, both env examples, `Dockerfile`, `tests/Unit/ReleaseVerificationPayloadTest.php`, `app/Services/Releases/Verification/{VerifiedArticle,ArticleSource,NntpArticleSource,PayloadVerifier,VerificationResult}.php`.

**Interfaces:** `ArticleSource::fetch(string $messageId): string`; `PayloadVerifier::verify(array $manifest, string $directory, callable $heartbeat): VerificationResult`; `VerificationResult` carries verdict, reason and the available verified manifest. `VerifiedArticle::decode(string $raw): self` exposes name, size, offset, part number, checksum and decoded bytes.

- [x] Write tests for complete multipart coverage, CRC errors, conflicting identities, missing segments, capacity limits and unavailable articles.
- [x] Run tests and confirm failures identify missing behavior.
- [x] Implement bounded individual-article downloads, validated yEnc metadata, resumable per-article checkpoints, scratch reservations, rate limits and heartbeat callbacks.
- [x] Add PAR2 system package; use actual repair followed by verification, never volume-name estimates. Test real fixtures for repair success, corruption and mixed sets.
- [x] Run payload tests, lint and Pint; record results.

## Task 2: Durable candidate manifests and leases

**Files:** new migration `create_release_verification_candidates_table`, `app/Services/Releases/Verification/{CandidateRepository,ManifestStore}.php`, `tests/Feature/ReleaseVerificationCandidatesTest.php`.

**Interfaces:** `CandidateRepository::archive(int $collectionId): void`, `claim(): ?object`, `heartbeat(int $id, string $token): void`, `finish(object $claim, VerificationResult $result): void`; `ManifestStore::write(array $manifest): string`, `read(string $path): array`.

- [x] Test transactional archive, exact declared counts, late cross-group merge, duplicate headers not resetting expiry, lease expiry, and version invalidation.
- [x] Create migration with unique collection identity, immutable manifest reference/hash, next-attempt time, lease token/expiry, last unique-arrival time, verdict and publication ID.
- [x] Atomically write compressed manifests before committing references; lock collection rows before ingest writes and snapshot/deletion. Merge only consistent file/segment identities; conflicts remain unpublished.
- [x] Archive descendants only after durable manifest transfer. Bound queue capacity under a shared reservation lock; overflow keeps original headers and pauses processing rather than publishing.
- [x] Run repository tests with SQLite, plus isolated MariaDB concurrency tests.

## Task 3: Publication gate and verified NZB writer

**Files:** `app/Services/ReleaseCreationService.php`, `app/Services/ReleaseProcessingService.php`, `app/Services/Binaries/HeaderStorageService.php`, `app/Services/Releases/Verification/{VerifiedReleasePublisher,VerifiedNzbWriter}.php`, `tests/Feature/VerifiedReleasePublicationTest.php`, existing eligibility tests.

**Interfaces:** `VerifiedReleasePublisher::publish(object $claim, VerificationResult $result): ?int`; publisher locks the candidate and validates token/version/hash before DB insertion. `VerifiedNzbWriter::write(array $manifest, string $guid): string` writes the exact proven available segments.

- [x] Test no release row is created for merely Sized or stale collections; complete headers alone remain pending.
- [x] Preserve declared counts during reconciliation. Send candidates to archive instead of legacy publication when verification is required (default true).
- [x] Reuse existing cleaning, categorization, duplicate detection and release insertion contracts. Write verified NZB before committing its release row; persist successful candidate publication and group memberships in the same transaction.
- [x] Test duplicate worker, stale result, missing NZB write, late-arrival race and repairable NZB contents.
- [x] Run affected release/NZB/ingestion tests and record compatibility-mode behavior explicitly.

## Task 4: Worker, backlog audit and monitoring

**Files:** `app/Console/Commands/{VerifyReleaseCandidates,VerifyExistingReleases}.php`, `routes/console.php`, verification audit helpers, command tests.

**Interfaces:** `releases:verify-candidates --limit=1`; `releases:verify-repairable --dry-run --limit=100 --since-id=0`, plus explicit delete option for proven bad releases. Both reuse the payload verifier.

- [x] Test lease recovery, 48-hour expiry, unknown retry and command limits.
- [x] Schedule one bounded verifier with overlap protection; existing-release audit runs manually with a persisted cursor/report and lower priority.
- [x] Parse existing NZBs safely; require metadata sufficient to prove completeness. Never delete outage/unknown results. Use ReleaseManagementService for explicitly requested destructive audit.
- [x] Report pending counts/age, verdict counts and scratch usage through the worker's status output and existing monitoring interfaces.
- [x] Run command tests and confirm Artisan command discovery.

## Task 5: Validation and handoff

- [x] Run affected PHPUnit files, Pint until clean, full PHPStan, and PHP syntax lint.
- [x] Run actual local PAR2 fixtures and isolated MariaDB race checks; record any missing environment prerequisites rather than using production state.
- [x] Obtain a fresh whole-change code review, fix substantive findings, rerun affected checks.
- [x] Update this plan with completed steps and evidence; stage all created/modified project files explicitly.
- [x] Leave deployment and live enablement to the designated deployment session. Report implementation and deployment separately.

## Validation evidence (October 11, 2026)

- Fork `master` synchronized at `8eeeaf547`; the fork has no `main` branch. GitHub confirms that incoming commit is authored by `xbmc4lyfe` and Verified. No GitHub writes or feature commits were made.
- Full PHPUnit run with `memory_limit=2G`: 2,502 tests, 20,519 assertions, 63 notices, 95 skips; no failures. The default 128 MiB test process exhausted memory; the larger-memory run completed.
- Affected release/import/ingestion tests: 134 tests, 511 assertions; 16 notices and two SQLite-specific skips. Later correctness regressions were tested separately.
- Isolated MariaDB checks: nine tests, 32 assertions; no failures. The isolated database was used exclusively for validation.
- Independent review: all substantive findings repaired; final reviewer reported no remaining P1/P2 findings and passed 48 focused tests / 106 assertions.
- PHP syntax checks and scoped Pint pass. Pint's `--dirty` cannot discover the host Git worktree from the isolated container, so changed PHP files were passed explicitly.
- Full PHPStan finds the same ten errors as a clean fork baseline, in ReleasesOptimizePreflight, User, MovieService, NNTPService, SearchCursorCodec and TraktService. No baseline entries were added and no new verification errors remain.
- Runtime package addition: PAR2 in both Docker build and production stages; no Composer/npm dependency changes.
- Imports join the same verification queue with a `queued` outcome. Published collection hashes no longer discard later uploads; they start a new candidate generation.
- Final late-arrival comparison includes metadata changes and ignores already-manifested duplicates. Incomplete touched collections are made eligible for immediate archival, never immediate publication.
- Capacity backpressure leaves room for a bounded manifest before the hard limit. Unsupported/malformed encoding remains unknown, including during destructive audit. Real checksum corruption can proceed to actual PAR2 repair.
- Changes are staged for maintainer review. Shared-stack deployment and live verification remain with the designated deployment session.

Final focused delivery checks: 50 tests, 116 assertions; Pint clean on repeated run and all application PHP syntax valid. Monitoring exposes backlog/outcomes, pending bytes, scratch allocation and verified throughput.

## Identity-recovery extension

Three independent research agents tested PreDB normalization, constrained fuzzy matching and payload-derived identities. Publication now retains integrity-verified yEnc names and PAR2-protected names, resolves only one consistent plausible title, preserves already named subjects and existing PreDB identity, and links newly recovered names only when a stored full PreDB title agrees exactly. Conservative NFC/case/separator keys compare candidate names without erasing years, episodes, editions, hyphens or accents.

`releases:identity-suggestions` provides bounded, database-revalidated fuzzy candidates. It is read-only and reports unresolved release variants; it never changes names or assigns a PreDB ID. Partial fuzzy probes recovered 28 baseline-missed suggestions but an adversarial omission replay found 146 wrong variant assignments, so that tier must not auto-link. No new global normalized PreDB index is claimed: current search result limits cannot establish global canonical-key uniqueness.

The research replay used 1,000 real PreDB titles and synthetic corruption, plus actual PAR2/yEnc fixtures. Research counts are conditional selector evidence, not observed production recovery rates. Live validation follows deployment. CI installs PAR2 and uses the memory allowance required by the full suite. Research and live samples are excluded from Docker build context.

Deployment sequencing is now explicit under the shared guard: build and fingerprint verification, pause all four application writers, migrate using the new image, then start the new runtime. A failed migration leaves processors paused and prevents new-code startup; it must be repaired before completing deployment. Shell command-order and migration-failure tests exercise this contract without touching Docker services.
