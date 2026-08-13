# Upstream Sync: Merge NNTmux master (August 2026), Preserving Fork Behavior

Status: ready for implementation
Upstream head at time of review: `aedfcf873` (NNTmux/newznab-tmux master)
Fork head at time of review: `86d4735c7`
Merge-base: `f1912e5e7`

## Problem Statement

The fork has drifted 15 non-merge commits behind upstream NNTmux while accumulating
38 commits of its own. Upstream now contains genuine bug fixes (TV season-pack
categorization, AniDB admin pages, admin request-input hardening, a
passworded-archive loop bug) and real operational improvements (a postprocessing
diagnostics command, structured processing outcomes and metrics, a download
deduplication cache, MB/GB size settings). None of this reaches the fork today.

A naive `git merge upstream/master` is not safe: upstream independently refactored
the additional-postprocessing subsystem around the *old* media-preview data
contract, so accepting it wholesale would silently revert the fork's non-RAR media
preview restore (PR #36/#37). Meanwhile, every future sync gets more expensive as
long as the merge-base stays pinned at the divergence point.

## Solution

Perform a **single true merge** of upstream master into the fork, with prescribed
conflict resolutions that adopt upstream's improvements while preserving the
fork's decisions: the plural, main-video media-preview contract; the
password-inspection eligibility gate; the design system; Edit Selected; and the
fork's agent documentation and workflow rules. The merge lands as a **merge
commit** (not a squash), permanently advancing the merge-base so future upstream
syncs shrink instead of compounding, and recording the deliberate rejections
(upstream agent docs, an unpinned dev dependency) exactly once.

## User Stories

1. As a site user browsing TV, I want full-season packs (bare `S01` tokens, with or without quality markers) categorized as TV rather than Movies, so season packs appear in TV searches and category browsing.
2. As a site user, I want season tokens preceded by year or edition markers still recognized as TV seasons, so unusual release naming doesn't leak seasons into the Movies category.
3. As a site admin, I want admin-area filter parameters coerced to scalars, so crafted array query strings cannot break admin pages.
4. As a site admin, I want the AniDB list page to show type, start date, end date, and rating for each entry, so I can evaluate entries without opening each one.
5. As a site admin, I want the AniDB edit page to load and save its fields correctly, so I can fix anime metadata.
6. As a site admin, I want site-wide release-size settings entered and displayed in MB/GB instead of raw bytes, so I don't mis-key magnitudes by a factor of a thousand.
7. As a site admin, I want existing stored size settings converted to bytes exactly once by migration, so effective behavior is unchanged after the upgrade.
8. As a site admin editing a group, I want the group's release floor and size limits presented in MB/GB, so per-group tuning is readable.
9. As a site admin, I want Edit Selected to keep working unchanged on the group list after the group-size UI changes, so bulk group tuning is not regressed.
10. As an indexer operator, I want a diagnostics command that reports postprocessing backlog, stale claims, claim-TTL fit against batch lifetime, worker capacity, required-index presence, and temp-path writability, so I can spot misconfiguration without reading code.
11. As an indexer operator, I want that diagnostics output available as JSON, so I can wire it into monitoring.
12. As an indexer operator, I want every postprocessed release to conclude with an explicit outcome (completed, passworded, group unavailable, no useful artifacts, timed out, deleted), so logs and metrics tell me what actually happened.
13. As an indexer operator, I want per-stage timing and persistence metrics for additional postprocessing, so I can identify slow stages and database hot spots.
14. As an indexer operator, I want repeat article downloads within one release deduplicated by a bounded cache, so bandwidth and NNTP time aren't wasted re-fetching identical segments.
15. As an indexer operator, I want small downloaded archives retained for the duration of a release's processing, so an NFO can be extracted without re-downloading the archive.
16. As an indexer operator, I want archive processing to stop as soon as one archive proves passworded, so passworded releases fail fast instead of grinding through remaining candidates.
17. As an indexer operator, I want search reindex requests coordinated per release instead of fired inline mid-processing, so the search backend isn't hammered with redundant updates.
18. As a site user, I want media previews for non-RAR releases to keep working exactly as restored in PR #36/#37 (frames extracted from the main video file using multiple segments), so previews don't silently regress during the sync.
19. As an indexer operator, I want the password-inspection eligibility gate preserved, so releases are not re-queued for password checking when inspection is disabled.
20. As a fork maintainer, I want upstream's dependency updates applied, so the fork stays current with security and compatibility fixes.
21. As a fork maintainer, I want no unpinned dependencies introduced by the sync, so supply-chain hygiene holds.
22. As a fork maintainer, I want the fork's agent documentation, workflow rules, and design-system conventions kept over upstream's versions, so fork conventions remain authoritative.
23. As a fork maintainer, I want the sync to land as a true merge that advances the merge-base, so the next upstream sync only presents genuinely new commits.
24. As a fork maintainer, I want deliberately rejected upstream changes recorded as resolved in merge history, so they never re-conflict on future syncs.
25. As a site admin, I want all re-applied admin views to respect the design system (primary tokens, shared components, dark-mode variants), so every color scheme and theme renders correctly.

## Implementation Decisions

**Strategy.** One merge of upstream master into a fork branch, resolved by hand,
landed through the normal PR flow. The PR **must merge as a merge commit, not a
squash** — squashing flattens the merge and the merge-base never advances,
defeating the strategy. The repository currently allows only squash merges, so
the implementer temporarily enables merge commits at the repository level for
this one PR, merges with the merge method explicitly, then restores the setting.
The `master-pr-only` ruleset (PR required, "PHP 8.5 via Sail" check) stays in
force throughout.

**Media-preview contract (the load-bearing resolution).** The fork's contract
wins everywhere: media-info message IDs are a *list* (multiple segments), and
detection targets the *main video file* by excluding explicit samples — never
the sample-only, single-segment contract upstream retained. Upstream's new work
planner is adopted but adapted: its work-plan value object carries the plural
list, its detection is inverted to main-video using the fork's restored
explicit-sample heuristic, and upstream's broadened sample-image extension
handling (png/webp) is absorbed rather than reverted. The fork's ffmpeg-based
video frame extractor and its requeue command are preserved untouched.

**Media extraction service.** Both sides added a constructor dependency — the
fork's video frame extractor and upstream's search-sync coordinator. Keep
both; the service-provider binding supplies both. The fork's extraction method
bodies win; upstream's swap of inline search updates for the coordinator is
adopted.

**Usenet download service.** Take upstream wholesale (its bounded per-release
cache and broader parameter types are compatible with the fork's callers; the
fork's only edits there were docblock annotations).

**Eligibility gate.** No action required — upstream does not touch the
password-inspection mode or processing configuration, and its new diagnostics
read backlog through the fork's patched candidate query, so the gate's
semantics flow into the new backlog reporting automatically. Verify via
existing claim/eligibility tests.

**MB/GB size settings.** Adopt fully, including the byte-conversion migration
and the size-unit helper. The unit-selector markup is re-applied onto the
fork's design-system versions of the admin site sections and group edit views
using the shared form components and primary tokens, with dark-mode variants.
Edit Selected semantics are unchanged.

**AniDB admin fixes.** Adopt both fixes, re-applying the view changes onto the
fork's restyled edit view. The list-page service change (extra columns,
SQLite-compatible aggregation) is taken as-is.

**TV season categorization and admin input hardening.** Adopt as-is; both merge
cleanly against the fork.

**Agent documentation.** Resolve upstream's AGENTS.md and agent-instruction
updates as "ours" — the fork's PR-only workflow, design-system rules, and doc
structure are deliberate divergences.

**Dependencies.** Adopt upstream's dependency state, then remove the
wildcard-pinned dev package upstream added (`laravel/pao` at `*`) through the
package manager so manifest and lock stay consistent. Frontend package updates
are adopted; rebuild assets afterward.

**Merge hygiene.** The example environment file is resolved as the union of
both sides' keys (per the repo rule that every env key ships with an example
entry). The static-analysis baseline is regenerated after the merge rather
than hand-merged. The settings seeder and cached routes are refreshed as
needed; the byte-conversion migration runs on deploy.

## Testing Decisions

**Seam:** the merged test suite itself. Upstream ships tests with nearly every
change (roughly half the refactor diff is tests); those verify the adopted
behavior. The fork's existing tests guard the preserved behavior. No new seams.

- A good test here asserts external contracts — the parser's output shape, the
  diagnostics command's exit code and JSON, categorization results, rendered
  admin pages, the work plan a planner emits — never internal call sequences.
- **Adopted-behavior tests (from upstream):** work-planner, processing-result,
  search-sync-coordinator, download-service cache, full-season categorization,
  size-unit conversion, admin site/group size settings, AniDB admin pages,
  admin scalar-input hardening. Upstream's planner and parser tests are the
  one adaptation point: they are rewritten to assert the plural, main-video
  media-ID contract instead of upstream's singular sample-only contract.
- **Preserved-behavior tests (fork, must keep passing unmodified):** video
  frame extractor, NZB content parser contract, orchestrator claim and
  eligibility, non-RAR preview processing, Edit Selected, design-system
  check script.
- Prior art: the existing unit suites under the additional-processing test
  namespace and the admin controller feature tests follow exactly these
  patterns.
- Verification runs in the container only (`./sail test` equivalent) — the
  host PHP passes tests the container fails due to a different pdo_sqlite
  build. The full suite is the gate, plus Pint and PHPStan per repo policy.

## Out of Scope

- Adopting upstream's AGENTS.md / agent-instruction content in any form.
- The `laravel/pao` dev dependency.
- Any new preview or postprocessing features beyond what the merge itself
  carries; no re-architecting of the preview pipeline.
- Upstream commits that land after the pinned upstream head — they are the
  next sync, which this merge makes cheaper.
- Changes to search infrastructure, Manticore/ES schemas, or the tmux engine.

## Further Notes

- **Conflict inventory** (against fork head `86d4735c7`, upstream head
  `aedfcf873` — 15 files): the example env file, AGENTS.md, the admin group
  controller, the additional-processing service provider, media extraction /
  NZB content parser / release processor / usenet download services, the
  PHPStan baseline, the AniDB edit view, the group edit view, three admin
  site section views, and the release-processor unit test. Everything else
  merges clean.
- Upstream's own history already resolved the overlap between its MB/GB
  commit and its refactor (they were parallel branches merged upstream), so
  this merge inherits that resolution; only fork-vs-upstream conflicts remain.
- The earlier per-commit review found one apparent conflict (the AniDB
  list-page commit) that was purely a cherry-pick ordering artifact; under a
  true merge it does not exist.
- The `upstream` remote (NNTmux/newznab-tmux) was added to the local clone
  during the review and is required for the merge.
- Full review and verification details live in the conversation that produced
  this spec; the decisive finding — that upstream's refactor retains the
  singular sample-only media contract and omits the video frame extractor —
  was verified directly against both trees at source level.
