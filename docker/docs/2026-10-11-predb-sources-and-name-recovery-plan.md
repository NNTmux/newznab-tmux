# Plan and spec for PreDB sources and name recovery

Status: proposed. Measurements are from the docker stack on 2026-10-11, about 30 hours after a fresh database. Rates marked "proxy" are estimates from release add dates, because the stages keep no completion timestamps.

## Where things stand

- The PreDB table holds about 153k rows, starting 2026-07-31. Every feed and IRC network is importing.
- Of 1.69M releases, about 3,200 (0.19%) are linked to a PreDB title.
- About 1.1M releases still carry a raw `[n/m] - "file" yEnc` subject or a hashed name.
- Of the 44k releases with scene-style names, most are P2P and web releases (`...AMZN.WEB-DL...-GROUP`). A sample of 25 of them was checked against predb.org, srrDB, xREL's P2P listing, Skwirll, and a public torrent index: 1 of 25 was found, on the torrent index.

Conclusion: more scene PreDB data does not raise the link rate much. Naming the hashed and raw releases through post-processing is the larger gain, and PreDB titles then help confirm the names it finds.

## Part A extra sources

### Goal

Add cheap redundancy and catch gaps when a primary feed is down, without adding load, or legal risk.

### Candidates

| Source | Type | New rows seen | Work |
|---|---|---|---|
| scnlog.me RSS | HTTP feed | 0 of 40 (all already present) | Small code change: add the key to `PredbFeedSourceFactory::availableKeys()` and map it to an `RssFeedSource` in `makeOne()` (it throws for unknown keys), add the endpoint to `config/predb_feeds.php`, then list the key in `PREDB_FEED_SOURCES` |
| opentrackers.org `#pre` | IRC | Not measured. The bots relay corrupt-net, zenet, and predb.se, which the stack already follows | Add a network entry to `config/irc_settings.php` (TLS 6697) and a parser for the `[PRE] [SECTION] name` line. Like every optional network it needs environment keys: `SCRAPE_IRC_OPENTRACKERS_ENABLED` (default `false`) plus server, port, and TLS keys, each with a comment in `.env.example` |
| ngp.re live stream | Pusher WebSocket plus HTML fragment | Overlaps the existing ngp IRC feed | Skip unless the IRC feed proves incomplete |

Rejected: predb.dev (its `robots.txt` disallows everything), Skwirll's dump (`/api/dump` is disallowed and its terms say not to build a database from the API), private-tracker announce channels (accounts and rules), and sources that are dead or bot-walled.

### Spec

1. Each new source is opt-in at first: present in config, absent from the default `PREDB_FEED_SOURCES` or `irc_settings` enabled list.
2. Measure before enabling. Run it for 24 hours and record the rows that the existing sources did not already have. A dry run loses its state between invocations (each poll would count the same item as new again), so either write to a scratch copy of the `predb` table, or log each poll's candidate titles to a file, deduplicate across polls, and compare them against `predb` once at the end.
3. Enable a source only if it adds at least 1% new rows per day or covers a gap during a primary outage. Otherwise leave it off.
4. Every source gets fixture-based tests for parsing (title, category, size, time, nuke state), as the existing sources have.
5. No source is polled faster than every five minutes. IRC sources join read-only and identify as the scraper does.

### Acceptance

- A new source's measured contribution is recorded in the PR.
- A per-source health check covers it. No such checker is in the repo yet (the one used while writing this plan was a local scratch script), so Part A starts by adding it as `docker/bin/predb-health`: container state, IRC scraper processes, and a probe of each feed endpoint. Source activity is judged by successful polls, received messages, or parsed-entry timestamps per source, not by rows attributed to it: `predb.source` is set only on a new insert, so a healthy redundant source (scnlog, opentrackers) often shows zero attributed rows because another source won the race. New sources extend it.
- Turning it off is a one-line config change.

## Part B: recovering names for hashed and raw releases

### Findings

| Stage | Pending | Done | Note |
|---|---|---|---|
| Additional processing (`passwordstatus=-1`) | 1,264,758 | about 426k | About 8.2k an hour (proxy) |
| NFO download (`nfostatus`) | 1,326,600 not yet tried (`-1`) | about 394k tried (`0`: no NFO found), 471 found (`release_nfos` rows) | Running, but few releases carry an NFO. `proc_nfo` is only the per-source "name already checked" flag, so it is not a measure of NFO acquisition |
| par2 (`proc_par2=0`) | 1,640,426 | about 51k | Not measured |
| Files (`proc_files=0`) | 1,685,025 | about 6k | `release_files` has about 13k rows |
| Renamed (`isrenamed=1`) | n/a | 7,108 | 5,369 outside the Other category |

Bottlenecks, ranked by effect:

1. **Size gate.** `min_size_to_post_process` is 50 MB (`AdditionalCandidateQuery.php`, size filter). 1,046,196 pending releases (62% of all) are 1 to 50 MB and are never candidates.
2. **Fix-names covers only the Other category.** `TmuxTaskRunner.php` runs `releases:fix-names ... --category=other`. That is 212k of 1.69M releases. `FixReleaseNames` already supports `--category=all`.
3. **Infrequent full-backlog fix-names passes.** The scheduled passes (`releases:fix-names` methods 3 to 20) read candidates in fixed 1,000-row batches, and the full-backlog (even) levels run at most once per 60 minutes (`tmux.fix_names.full_backlog_interval_minutes`). `fix_names_per_run` (100) is read only by `ForkingService` for `multiprocessing:fixrelnames predbft`, so it does not limit these passes.
4. **CPU saturation.** The indexer runs near 485% CPU and 83% of its memory, with post-processing at `nice` 19. Thread settings compete: post 16, NFO 10, fix-names 6, binaries 12, backfill 30.
5. **Per-cycle cap.** At most 16 GUID buckets per cycle, each worker takes up to 4 batches (`--max-batches=4`), and `max_additional_processed` is 100 per batch. With 16 post-processing threads that allows roughly 6,400 releases per cycle; raising the limit to 250 would allow about 16,000, so the CPU and NNTP forecast must use that figure.
6. **Order.** Newest first, so older releases wait behind the continuous inflow.

### Spec

Each step is independent and reversible. Take them in order, and check the metrics below before the next step.

**Step 1: settings only (no code).**
- Lower `min_size_to_post_process` from 50 MB to 10 MB. Watch load and NNTP connection use for an hour before going lower.
- Lower the full-backlog interval from 60 to 30 minutes by setting `TMUX_FIX_NAMES_FULL_INTERVAL=30`. This is read from the environment at bootstrap by the long-lived `tmux:monitor`, so it only takes effect after the indexer restarts (`make -C docker restart s=indexer`, as part of a single deploy). (Raising `fix_names_per_run` only speeds the PreDB full-text pass, so it is not part of this step.)
- Rollback is operational only: restoring the 50 MB gate stops the newly admitted 10 to 50 MB candidates and the extra load. It does not undo work already done in the meantime (`passwordstatus` and `haspreview` updates, release-file and media rows), which stay.

**Step 2: let fix-names cover all categories (small code change).**
- Make the category in `TmuxTaskRunner.php` configurable: `tmux.fix_names.category` in `config/tmux.php`, backed by a new `TMUX_FIX_NAMES_CATEGORY` key (default `other`, so behavior is unchanged until set to `all`). Add the key with that default and a short comment to `.env.example`.
- Run a one-off pass outside peak load, then switch the setting. It runs inside the Docker stack (the app environment is `docker/config/app.env` and `mariadb` only resolves on the Compose network). `releases:fix-names` takes one method number from 3 to 20 per call, so loop, and stop at the first failure so a skipped source (methods 7 and 8 need NNTP) is never reported as done:
  `for level in 11 12 17 18 3 4 5 6 7 8 9 10 13 14 15 16 19 20; do make -C docker artisan cmd="releases:fix-names $level --update --category=all --set-status" || exit 1; done`
- Order matters: with `--set-status`, the generic file and uid passes (methods 5, 6, 9, 10) set `proc_files` and `proc_uid` flags that the specialized passes (11, 12, 17, 18) also use to pick candidates, so the specialized ones run first in this loop. Confirm the exact flag dependencies in the implementation PR, and check the scheduled order in `TmuxTaskRunner` (ascending 3 to 20) for the same issue.
- Change the work predicate too. `Tmux::getTableCount(1)` computes `processrenames` from `Category::getCategoryOthersGroup()`, and `TmuxTaskRunner::runFixNamesTask()` only adds the recent (odd) passes when that count is nonzero. With `all`, count candidates across all categories (or run the recent passes unconditionally), otherwise non-Other work waits for a full-backlog slot.
- Tests: the task builds the right command for each value, and the recent passes are scheduled when only a non-Other release is pending.

**Step 3: relieve CPU.**
- Decide the trade-off with the owner: more CPU for the indexer, or fewer backfill and post-processing threads while the backlog clears.
- Apply any change that needs a container restart through one deploy. `make -C docker deploy` comes from the deploy guard PR (xbmc4lyfe/newznab-tmux#27); until that merges, a single designated session runs `make -C docker build` and then `make -C docker up` (not the root Makefile, which is the Sail stack).

**Step 4: optional tuning after the backlog shrinks.**
- Raise `max_additional_processed` and `max_nfo_processed` from 100 to 250 only after re-forecasting load with the per-cycle figure from bottleneck 5 (up to about 16,000 releases per cycle).
- Consider oldest-first ordering once inflow slows.

### Metrics

Record these before Step 1 and after each step (a saved query is enough):

- Raw-subject releases: `searchname LIKE '[%'`.
- `isrenamed = 1` count, split into Other, and not Other.
- `release_files` row count.
- NFO acquisition: `nfostatus` counts and `release_nfos` rows. The `proc_nfo`, `proc_par2`, and `proc_files` flags show which name sources have been checked, not whether downloads are happening.
- PreDB-linked releases.
- Indexer CPU and memory, and NNTP connection usage.

### Risks

- Lowering the size gate multiplies post-processing work on a CPU-saturated host. Step the value down and watch the load.
- More processing means more NNTP connections. Stay inside the provider's limit.
- `--category=all` scans more rows per pass and can lengthen DB queries. Run the one-off at a quiet time.
- Renames reset metadata ids (the name-fixing path does this by design), so expect re-processing after a bulk rename.

### Open questions for the owner

1. Is it acceptable to lower the size gate toward 10 MB, given the CPU cost?
2. Should the indexer get more CPU, or should thread counts come down while the backlog clears?
3. Should fix-names run on all categories permanently, or only for a one-off backlog pass?

## Order of work

1. Merge the deploy guard (#27) so deploys stop restarting the stack mid-run.
2. Part B step 1 (settings), then measure.
3. Part B step 2 (code), then the one-off pass.
4. Part A sources in any order; each is small and independent.
