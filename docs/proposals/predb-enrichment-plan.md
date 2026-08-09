# RFC: PreDB Enrichment — Dump Import, Hash Matching, and predb.net Lookups

| | |
| --- | --- |
| **Status** | Draft — idea captured, not yet scoped |
| **Author** | Claude (investigation session with the maintainer) |
| **Created** | 2026-08-09 |
| **Decision** | None yet — becomes ADR material once scoped and accepted |

This RFC captures the investigation and recommendations from an August 2026
architecture discussion, so the work can be scoped later without re-deriving
the analysis. It is part spike report (measured data, constraint findings)
and part proposal (the phased plan).

Related reading: [../architecture/indexing-pipeline.md](../architecture/indexing-pipeline.md)
(especially "The three regex tables" and the release-creation flow).

## Current state (verified August 2026)

- **The only PreDB ingestion path is the IRC scraper** (`irc:scrape` →
  `app/Services/IRCScraper.php`). It captures pres announced *while
  connected* — it provides no history. A release posted before scraping
  started can never match.
- **All matching is local.** `Predb::matchPre()` (exact title/filename at
  release creation), `predb:check` (title = searchname backlink), the
  `predbft` full-text fix-names pass (Manticore/ES `predb_rt` index), and
  `match:prefiles` (release_files vs `predb.filename`) all query only the
  local `predb` table. **No code calls any external PreDB API** — the
  README's "PreDB API Alternatives" section is informational only.
- **Vestiges from the removed nZEDb machinery:**
  - `predb_imports` table + `PredbImport` model exist but nothing reads or
    writes them (the import scripts lived in the deleted `misc/` tree).
  - `PredbHash` model and `Predb::hashes()` relation exist, but the
    `predb_hashes` table is gone from the schema; its maintaining triggers
    were dropped in `2025_01_30_115835_drop_triggers.php`. The `ishashed`
    detection on releases is also gone. Hash-based de-obfuscation is
    entirely dead code today.

## Constraints discovered

- **predb.net API terms prohibit using the API to fill/clone a database.**
  It is for targeted lookups only. Any bulk paging strategy against it is
  off the table.
- **predb.ovh appears defunct** (DNS `ESERVFAIL` as of 2026-08-09). Its
  monthly SQL dump (`predb.ovh/download/`) would have been the ideal bulk
  source; only its API docs survive at
  <https://predbdotovh.github.io/pre-api/>.
- **The nZEDb dumps are the clean bulk source.**
  <https://github.com/nZEDb/nZEDbPre_Dumps> — automated daily snapshots of
  the nZEDb IRC predb bot, published explicitly for indexers to import.
  Discontinued but still available: 3,596 daily CSV files across 13
  folders, covering July 2014 → May 2024. Older history exists in one-time
  archives (archive.org `predb`, supertorrents dump, Defacto2 1999–2007
  dump) if ever wanted.

### Measured dump characteristics (sampled 2014 / 2019 / 2024 files)

| Metric | Value |
| --- | --- |
| Daily files | 3,596 (each = last 24 h of pres) |
| Rows/day | ~2,100–2,900, avg ≈ 2,400 |
| Total rows | ≈ 8.6 M gross; ≈ 8 M after unique-title dedupe |
| Avg row size | ≈ 152 bytes (raw CSV ≈ 1.3 GB total) |
| Est. InnoDB footprint | ≈ 3–5 GB (`predb` carries 7 secondary indexes incl. unique `title` + FULLTEXT `filename`) |
| Est. search-index cost | +1–2 GB if `predb_rt` (Manticore/ES) is populated |
| Format | Tab-delimited CSV, quoted fields, `\N` nulls; columns match `predb_imports` (title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname) |

Reference import workflow (column mapping, batch loop):
`https://github.com/nZEDb/nZEDb/blob/0.x/cli/data/predb_import_daily_batch.php`

## Recommended division of labor

> **Query APIs for targeted lookups; published dumps for bulk backfill.**

The design principle that makes all of this cheap: ingestion is fully
decoupled from matching. Anything that lands rows in `predb` — dump import,
IRC, API lookup — is picked up by the existing matching machinery
(`matchPre`, `predb:check`, `predbft`, `match:prefiles`) with zero changes.

### Phase 1 — `predb:import`: bulk-import the nZEDb dumps

One-time backfill of 2014–2024 pre history.

1. Download/iterate the 3,596 daily `*.csv.gz` files (support a local
   directory of pre-fetched files; don't hammer raw.githubusercontent.com).
2. Per file: `LOAD DATA [LOCAL] INFILE` into the existing `predb_imports`
   staging table. **Do not use Eloquent** — 8.6 M rows through the ORM is
   hours; `LOAD DATA` is minutes.
3. Merge: `INSERT ... SELECT` from `predb_imports` into `predb` with
   `ON DUPLICATE KEY UPDATE` (unique `title` index absorbs cross-day and
   cross-source duplicates; keep the earliest `predate`). Resolve
   `groupname` → `groups_id` during merge, as the reference script did.
4. Truncate `predb_imports` between batches to bound its size.
5. Afterwards: `nntmux:populate-search-indexes --predb` to refresh
   `predb_rt`, then one full `predb:check` + `predbft` cycle to retro-link
   existing releases.

Idempotent by construction (unique title), so it can be re-run or resumed.

### Phase 2 — Bring back hash matching (app-side, no triggers)

**Why:** hashed-name obfuscation (subject/file names = `md5(title)` or
`sha1(title)`) was rampant ~2014–2020 — exactly the window the dumps cover.
Hash lookup is the *only* naming mechanism that works when nothing readable
survives in a post, and unlike fuzzy predb matching it is exact — no
`PredbMatchSelector`-style false-positive policing needed. Post-2020
obfuscation trends toward random strings, so value concentrates on
backfilled older content.

**Cost:** ~8 M pres × 2 hashes (md5 + sha1 of `title`) ≈ 16 M skinny rows,
~1.5–2 GB with index.

**How (deliberately not the old trigger design** — triggers were dropped on
purpose in Jan 2025; keep logic in application code where it is testable):

1. Recreate a minimal `predb_hashes` table: `predb_id` FK, `hash`
   (VARBINARY(20), or split md5/sha1 columns), indexed on `hash`.
   The orphaned `PredbHash` model becomes live again.
2. Generate hashes in three places:
   - the Phase 1 importer (bulk `INSERT ... SELECT MD5(title), SHA1(title)`),
   - `IRCScraper::_insertNewPre()` / `_updatePre()` (keep current),
   - a `predb:hash --regenerate` command for repair/backfill.
3. New name-fixing source `hash`: detect `/^[0-9a-f]{32}([0-9a-f]{8})?$/i`
   in `releases.searchname` and `release_files.name`, look the hex up in
   `predb_hashes`, rename on hit (set `isrenamed`, `predb_id`, recategorize
   — same post-rename flow as the other sources). Wire it into the
   `releases:fix-names` level scheme and the tmux fix-names pane.

### Phase 3 — predb.net targeted lookup queue (ToS-compliant use)

Per-release *search* is what predb.net's API is for. This fills the gap the
dumps can't: pres from ~May 2024 (dump end) until this deployment's IRC
scraper came online, plus anything IRC missed while down.

**Shape — asynchronous, never inline:**

- **Do not** call the API synchronously in `ReleaseCreationService` — misses
  dominate usenet volume (obfuscated/spam posts that match nothing), the
  release loop runs in parallel workers on a ~60 s cadence, and per-release
  HTTP round-trips would both stall the pipeline and blow through their
  rate limits.
- Trigger from the **name-fixing pipeline instead of creation**: by the time
  fix-names runs, NFO/par2/file-derived candidate names exist — far better
  query material than the cleaned subject at creation time.
- Queue candidate names that missed locally; a scheduled job drains the
  queue at a polite rate (single-digit requests/sec at most; honor their
  documented limits and any API-key requirements).
- **Cache negative results** (e.g. a `predb_lookups` table with
  name-hash + last-checked). Fix-names revisits the same releases every
  cycle; without a "we already asked" marker the same misses re-query
  forever. Re-ask only after a long TTL, if ever.
- Guard fuzzy API results with the existing
  `app/Services/NameFixing/PredbMatchSelector.php` scorer — remote search
  results have the same junk-match failure mode it already solves for
  Manticore.
- On hit: insert the pre into `predb` (and its hashes, per Phase 2), then
  let the normal matching machinery link it. The lookup service never
  touches releases directly.

Over time the table accretes exactly the pres this indexer's releases
need — defensible fair use, and a fraction of the traffic bulk paging would
have been.

## Explicit non-goals

- No bulk paging/mirroring of predb.net (or any query API) — ToS violation.
- No database triggers for hash maintenance.
- No synchronous external calls anywhere in the release-creation loop.

## Open questions for scoping

1. Which hash variants? nZEDb also hashed some title variants
   (case/separator normalization). Start with md5 + sha1 of the verbatim
   title; add variants only if hit-rate data justifies it.
2. Does Phase 3 need an API key / account with predb.net, and what are the
   current documented rate limits? (Their docs page is JS-rendered; confirm
   terms manually before building.)
3. Pre-2014 history: worth importing the archive.org / Defacto2 sets, or is
   2014+ enough for the groups this deployment backfills?
4. `predb` table growth policy: with ~8 M imported rows plus live IRC and
   Phase 3 accretion, is any pruning wanted, or is the ~5–7 GB steady state
   acceptable? (Sole-deployment context suggests: acceptable.)
5. Sequencing: Phases 1 and 2 land naturally as one PR (importer + hashes +
   hash fix-names source); Phase 3 is independent and can wait for evidence
   that the post-2024 gap actually hurts match rates.
