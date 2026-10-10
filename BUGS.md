# Bugs found while building the docker/ stack

These bugs turned up while building and running the self-hosted stack in `docker/` (see `docker/docs/`). Each entry was verified against the code at `0ffee3e9f`, or observed on the running stack.

**Status key.** Each entry is **Open** (not fixed), **Fixed** (fixed in this working tree, changes staged), **Mitigated** (worked around in the docker/ stack, upstream code unchanged).

## App code

### 1. `UsenetGroup::deleteGroup()` throws a TypeError after deleting the group (Open)

- **Where:** `app/Models/UsenetGroup.php:316-321`. Called from `AdminAjaxController.php:96`, which handles the administrator action that deletes a group.
- **Bug:** the method is declared `: bool`, but it returns `self::query()->where('id', $id)->delete()`, which is an `int`.
- **Symptom:** `Return value must be of type bool, int returned`. By the time the error fires, the purge and the delete have already happened, so the administrator UI reports an error for an action that actually succeeded.
- **Reproduce:** call `UsenetGroup::deleteGroup($id)` for any group. This surfaced while pruning groups.
- **Fix:** `return self::query()->where('id', $id)->delete() > 0;`

### 2. Purging or deleting a group leaves its collections, binaries and parts behind (Open)

- **Where:** `UsenetGroup::purge($id)` → `reset($id)` (`app/Models/UsenetGroup.php:330-345, 390-410`).
- **Bug:** `reset($id)` deletes only `missed_parts` for the group. `collections` rows are removed only by `resetall()`, which truncates every group.
- **Symptom:** after deleting a group, its unfinished collections stay in the database with a `groups_id` that no longer exists. Binaries and parts cascade from collections, so they stay too. Pruning about 40k groups left **16,756** orphaned collections that had to be deleted by hand.
- **Fix:** in `reset($id)`, run `Collection::query()->where('groups_id', $id)->delete()`. The binaries and parts foreign keys are `ON DELETE CASCADE`, so this also removes them.

### 3. Music and Games browse pages show broken cover images (Fixed)

- **Where:** `resources/views/music/index.blade.php:116` and `resources/views/games/index.blade.php:119`.
- **Bug:** the views build the image URL as `url('/covers/music/' . $result->cover)`. But `cover` is only a 0/1 "has cover" flag; the file is stored as `covers/{type}/{id}.webp|jpg`.
- **Symptom:** every cover requests `/covers/music/1`, which doesn't exist, so the browser shows the alt text.
- **Fix (staged):** use `getImageAssetUrl('music'|'games', (string) $result->id, url('/images/no-cover.png'))`, the same approach as the books view. A regression test was added in `tests/Unit/CoverBrowseComponentsTest.php`.

### 4. The scheduled `tmux:health-check --auto-restart` starts a second tmux session when the scheduler runs in its own container (Mitigated)

- **Where:** `routes/console.php`, the `tmux:health-check --auto-restart` schedule entry, and `deploy/cloud/compose.yaml`, which runs separate `scheduler` and `indexer` containers.
- **Bug:** the scheduler container can't see the indexer's tmux server, so it finds no session and calls `tmux:start` inside the scheduler container.
- **Symptom:** a second indexing session starts and uses the same database and provider.
- **Mitigation (staged):** new config `tmux.scheduled_health_check` (`TMUX_SCHEDULED_HEALTH_CHECK`, default `true`) gates that entry. The docker/ stack sets it to `false`, and its indexer supervises itself with `tmux:health-check --auto-restart`. `deploy/cloud` should do the same.

### 5. Horizon doesn't consume the mail queues by default (Mitigated)

- **Where:** `config/horizon.php:202` (`HORIZON_QUEUES` default `api-audit,default`).
- **Bug:** jobs are dispatched to `emails`, `incidents`, `contactemail` and `newreg`. Sources: `app/Mail/InvitationMail.php:26`, `app/Mail/IncidentDetected.php:24`, `app/Models/User.php:1278,1373`, `ContactUsController.php:36` and `UserServiceObserver.php:32`.
- **Symptom:** invitation, expiry, contact-form, registration and incident emails sit in Redis and are never sent.
- **Mitigation:** the docker/ stack sets `HORIZON_QUEUES=api-audit,default,emails,incidents,contactemail,newreg,welcomeemails`. Upstream should either change the default or derive it from the configured queue names.

### 6. `nntmux:install` always warns that the unzip path isn't configured (Open)

- **Where:** `app/Console/Commands/InstallNntmux.php:195,218`.
- **Bug:** it reads `config('nntmux_settings.tmp_unzip_path')`, but the key is `nntmux.tmp_unzip_path` (`config/nntmux.php:78`).
- **Symptom:** the warning always prints, and `updatePaths()` returns early without checking or creating the path.
- **Fix:** read `config('nntmux.tmp_unzip_path')`.

### 7. `nntmux:install` replaces an existing `APP_KEY` (Open, design)

- **Where:** `app/Console/Commands/InstallNntmux.php:85` (`key:generate --force`).
- **Symptom:** re-running the installer on a configured `.env` makes existing encrypted data and sessions unreadable. `nntmux:deploy-init` doesn't do this.
- **Fix:** only generate a key when `APP_KEY` is empty.

### 8. `nntmux:populate --disable-keys` silently skips its buffer tuning (Open)

- **Where:** `app/Console/Commands/NntmuxPopulateSearchIndexes.php:1244`.
- **Bug:** `SET SESSION innodb_buffer_pool_size = …` is invalid, because that variable is global-only. The error is caught, which also skips the `bulk_insert_buffer_size`, `read_buffer_size` and `sort_buffer_size` statements after it.
- **Fix:** drop the `innodb_buffer_pool_size` line, or wrap each statement in its own try/catch.

### 9. Dead configuration keys (Open, low priority)

- `NNTP_CONNECTIONS_A` is read into `NNTPService::$_alternateNntpConnections` (`NNTPService.php:172`) but never used.
- `lame_path` (`LAME_PATH`) is defined in `config/nntmux_settings.php`, but nothing in `app/` references it.
- `FFProbe::create()` (`MediaExtractionService.php:481`) ignores `FFPROBE_PATH`. It finds `ffprobe` on `PATH` instead.

### 10. Misleading forced-logout message (Open, low priority)

- **Where:** `app/Http/Middleware/EnforceSessionToken.php:45`.
- **Bug:** a new session token is issued by both password login (`LoginController.php:315`) and passkey login. Any login therefore logs out the user's other sessions, but the message always blames a passkey login on another device ("a passkey login was started on another device").
- **Symptom:** confusing when two browsers or tabs, or a script, log in as the same user. This made the Grafana debugging look like an auth failure.

### 18. `CHECK_PASSWORDED_RARS=false` (the default) silently disables all additional post-processing (Open)

- **Where:** `app/Models/Release.php:207`: `$passwordStatus = config('nntmux_settings.check_passworded_rars') === true ? -1 : 0;`. The default is `false` in `config/nntmux_settings.php:6` and `.env.example:339`.
- **Bug:** `passwordstatus = -1` is the only "needs additional processing" marker. `AdditionalCandidateQuery::applyPredicates()` (`app/Services/AdditionalProcessing/AdditionalCandidateQuery.php:107`) selects `passwordstatus = -1 AND haspreview = -1`. With the flag off, every new release is inserted with `passwordstatus = 0`, so it never enters the queue.
- **Symptom:** none of the additional-processing work ever runs:
  - no ffmpeg samples or previews, and no mediainfo (`video_data` and `audio_data` stay empty)
  - no RAR/ZIP file lists (`release_files`) and no JPG previews
  - no PAR2/file-name based renames

  The tmux pane only reports "no additional work" and nothing else. On the docker stack, 480,988 releases built up with zero media data, and 99% sat in Misc, because obfuscated posts can only be renamed from their PAR2 and file lists.
- **Fix:** decouple the queue marker from the password-check feature. Always insert `-1`, and let `ProcessingConfiguration::$processPasswords` decide only whether a password result is recorded; `ReleaseFileManager.php:274` already does this. At minimum, document that the flag gates all post-processing.

### 19. Every `unrar` extraction fails: a lone `-` is passed as the end-of-switches marker (Fixed)

- **Where:** three call sites:
  - `app/Services/AdditionalProcessing/ArchiveExtractionService.php:236`: `'-@', '-',` in `extractArchive()`
  - `ArchiveExtractionService.php:399`: `extractSpecificFileWithExternalTools()`
  - `app/Services/NfoService.php:678-679`: `extractNfoViaUnrar()`
- **Bug:** `unrar` (6.21 confirmed) treats the lone `-` as an unknown option. It prints `ERROR: Unknown option:` and exits 7 before opening the archive. RAR's documented end-of-switches marker is `--`.
- **Symptom:** nothing is ever extracted from RAR archives, so mediainfo, ffmpeg samples, video previews, inline JPGs and NFOs inside RARs are never produced. File lists still appear, because they come from the PHP RAR parser. Pane output shows `(cB)` downloads with no `(vRAW)`/`m`/`s`.
- **Reproduce:** run `unrar e -ai -ep -c- -id -inul -kb -or -p- -r -y -@ - first.rar out/`. It exits 7. With `--` it extracts. On a real release, the `--` form pulled 2.3 MB of an `.mkv` from the first volume, and mediainfo read it as HEVC 1920×1072.
- **Fix (staged):** use `'--'` at all three sites. Regression test: `tests/Feature/UnrarArgumentsTest.php`, using the stored-RAR fixture `tests/Fixtures/archives/stored-sample.rar`. After the fix, the live pipeline produced `(vRAW)`, `m` and `s`, with rows in `media_infos`, `video_data` and `audio_data`, and a preview.

### 20. `extract_using_rar_info = 1` turns off all archive extraction without saying so (Open, documentation)

- **Where:** `post_processing_configurations.extract_using_rar_info`. It is read in `ArchiveExtractionService::extractArchive()` and `prepareExtractionDirectories()`. Its default is `false` (`database/support/LegacySettingsManifest.php:121`).
- **Bug:** with it on, only the PHP RAR/ZIP parser runs. It lists the files but extracts nothing, so mediainfo, samples, and previews never get a file.
- **Symptom:** this stack's database had it at `1`, even though the manifest default is `false`; what set it is unknown. That hid bug #19, because `unrar` was never even called.
- **Fix:** label the administrator setting so it's clear that enabling it disables media extraction. The docker stack's `tuning.sql` now sets it to `0`.

### 21. The alternate NNTP provider can't be used as an article fallback alone (Fixed)

- **Where:** `config('nntmux_nntp.use_alternate_nntp_server')` (`USE_ALTERNATE_NNTP_SERVER`). The header, backfill and post-process commands (for example `UpdateGroupHeaders.php:66`, `UpdatePostProcess.php:133`) connect to the alternate when it is true, and the article fetchers (`ProcessingConfiguration`, `PostProcessService`, `NfoService`, `NzbContentsService`) pass the same flag to `NNTPService::getMessagesByMessageID()`/`getMessages()` as the retry-on-alternate switch.
- **Bug:** one flag means both "connect to the alternate" and "retry missing articles on the other provider." There is no way to keep headers on the primary and still fetch articles missing there from the alternate.
- **Symptom:** with `USE_ALTERNATE_NNTP_SERVER=false` (the docker/ stack, which keeps headers on the primary), articles missing on the primary fail post-processing even though alternate credentials are configured.
- **Fix (staged):** new `nntmux_nntp.alternate_article_fallback` (`NNTP_ALTERNATE_FALLBACK`, blank = follow `USE_ALTERNATE_NNTP_SERVER`, so upstream behavior is unchanged) drives the four article fetchers. The docker/ stack sets it to `true`. Test: `tests/Feature/NntpAlternateFallbackConfigTest.php`.
### 22. Every per-group release job runs a full-table orphan-collection scan (Fixed)

- **Where:** `ReleaseProcessingService::runReleaseCreationLoop()` (`app/Services/ReleaseProcessingService.php:276`) calls `deleteCollections($groupId)` on every iteration of every group job. `deleteCollections()` (`:858`) ignores the group and calls the global `CollectionCleanupService::deleteFinishedAndOrphans()`, whose `deleteOrphanCollections()` (`app/Services/CollectionCleanupService.php:159-166`) runs `SELECT c.id FROM collections c WHERE NOT EXISTS (SELECT 1 FROM binaries b WHERE b.collections_id = c.id) ORDER BY c.id LIMIT n`.
- **Bug:** when there are few or no orphans, that query probes `binaries` once for every row in `collections` before it can return. The work is global, but it's repeated once per group job.
- **Symptom:** with 16.4M collections on the docker stack, each scan ran for 6+ minutes. The four release workers each held one at the same time (`SHOW PROCESSLIST`), and a release cycle has 158 group jobs. MariaDB sat at ~320% CPU, and release creation dropped from ~146k/hour to ~25k/hour.
- **Fix (staged):** `deleteOrphanCollections()` takes a non-blocking `Cache::lock`, so only one worker sweeps at a time and the rest skip. Each sweep checks one id window after a cursor stored in the cache, then advances it and wraps to the start at the highest id. The window defaults to 250,000 ids (`nntmux.cbp.orphan_scan_window`, `CBP_ORPHAN_SCAN_WINDOW`). Regression tests are in `tests/Feature/CbpCleanupServiceTest.php`.

### 23. `nntmux:resetdb` leaves release-linked tables behind and wipes the PreDB search index (Open)

- **Where:** the table list and `Search::truncateIndex()` call in `app/Console/Commands/NntmuxResetDb.php:67-91, 279-282`.
- **Bug:** it truncates `collections` with foreign key checks off but not `collection_groups`, so the cross-post rows survive. Collection ids restart at 1 after the truncate, and `CollectionHandler` writes `collection_groups` with `INSERT IGNORE`, so new collections inherit the old group names. It also skips `release_regexes`, `videos_aliases`, `par_hashes`, `release_unique`, `release_reports`, `release_nzb_creation_failures`, `release_nzb_passwords`, `bookinfo`, `consoleinfo` and `gamesinfo`. Separately, it truncates `predb_rt` while keeping the `predb` table, so PreDB search is empty until the index is repopulated.
- **Symptom:** on the docker stack, a reset would have left 45M `collection_groups` rows and 938k `release_regexes` rows pointing at deleted ids.
- **Fix:** add the missing tables to the list, and drop `predb_rt` from `truncateIndex()` (or repopulate it afterwards). The 2026-10-09 wipe on the docker stack was done with an equivalent SQL script that includes these tables, because the command only runs when `APP_ENV=local`.

### 24. The IRC scraper's only channel announces no pres, and it can't read the active pre channels (Fixed)

- **Where:** `app/Services/IRCScraper.php` (one server from `scrape_irc_server`, one message pattern for the NNTmux bot's `NEW: [DT: …]` format) and the `SCRAPE_IRC_CHANNELS` default `#PreNNTmux`.
- **Symptom:** on 2026-10-09, a 2-minute capture of synirc `#PreNNTmux` showed only the bot's "still active" message, and the running `irc:scrape` stored no pres in 12 minutes. `#nZEDbPRE`'s topic reads "DEAD." In the same window, corrupt-net `#pre` and zenet `#pre` each announced about 15 pres. The scraper can't use them: it connects to only one server, and those channels use different formats (`PRE: [FLAC] Name-GRP` and `(PRE) (MP3-WEB) (Name-GRP)`).
- **Fix (staged):** `irc_settings.networks` profiles (`synirc`, `corruptnet`, `zenet`, and `predatabase`, which is off by default). Plain `irc:scrape` supervises one child process per enabled network (`irc:scrape --network=<key>`). The public channels are parsed by `PreAnnounceParser` and stored through `PredbFeedImporter`. zenet's TLS certificate doesn't name `irc.zenet.org`, so that profile turns off host name verification only. Tests: `tests/Unit/Predb/PreAnnounceParserTest.php` and `tests/Feature/IrcNetworksTest.php`.
### 25. `predb:import-feed --since` fails on predb.net after 100 pages instead of stopping (Fixed)

- **Where:** `app/Services/Predb/Feeds/Sources/PredbNetSource.php::fetch()` and the history loop in `app/Console/Commands/PredbImportFeed.php`.
- **Bug:** api.predb.net accepts only `page` 1 to 100 and has no date or offset parameter, so at most the newest 10,000 entries can be read. Page 101 returns HTTP 400 (`Invalid page number > Min: 1 > Max: 100`). The source turns that into an exception, so the run reports `failed` rather than `incomplete`.
- **Symptom:** on 2026-10-10, `predb:import-feed --source=predb_net --since=7d` inserted 1,899 rows and then reported `failed: HTTP request returned status code 400`. It only reached about 4 days back. predb.club covered the full 7 days.
- **Fix (staged):** `PredbNetSource::fetch()` returns no entries past `MAX_PAGE` (100), so a history import ends as `incomplete` instead of `failed`. Test: `tests/Feature/PredbImportFeedCommandTest.php`.

### 26. `irc:scrape` retries a banned IRC network every 30 seconds indefinitely (Fixed)

- **Where:** `IrcScraperCommand::supervise()` (fixed `RESTART_DELAY` of 30 s) and `IRCScraper::_startScraping()`, which calls `exit('…')` and so exits with code 0 on a failed login.
- **Bug:** a login refused with a G-line (`Closing Link: … Banned (G-Lined)`) is treated like any other exit, so the supervisor reconnects every 30 seconds for as long as the ban lasts.
- **Symptom:** on 2026-10-10 the host's VPN exit address (103.102.246.86, AS203020 HostRoyale) is listed in DroneBL as an open proxy. synirc and zenet G-line it, and the scraper reconnected to both twice a minute. That can get a ban extended.
- **Fix (staged):** `IRCScraper` exits with code 1 on a failed connect or login (`fail()`), and the supervisor backs off per network: 30 s doubling to 1 hour (`IrcScraperCommand::restartDelay()`), reset once a child stays up for 10 minutes. Test: `tests/Feature/IrcNetworksTest.php`.

### 27. The NNTP connection count only sees one of the provider's round-robin addresses (Open)

- **Where:** `Tmux::getConnectionsInfo()` (`app/Services/Tmux/Tmux.php:52`) resolves `NNTP_SERVER` once with `gethostbyname()`, which returns a single address. `Tmux::getUSPConnections()` (`:66-100`) then counts only `ss` lines containing that `ip:port`. The tmux monitor publishes the result, and `TmuxSnapshotCollector` exports it as `nntmux_nntp_connections`.
- **Bug:** news.newshosting.com resolves to several addresses (185.90.196.65, .97 and .129 on 2026-10-10), and each worker connects to whichever one its own lookup returned. Long-lived workers also keep connections to addresses that have since left DNS (85.12.62.250 and .251). Only sockets to the single address the monitor picked are counted.
- **Symptom:** the Grafana "NNTP connections" panel dropped from about 50 to 2-3 at around 21:50 UTC on 2026-10-09 and stayed there. At the same time, `ss` in the indexer showed 27-50 established connections to port 563, spread over four addresses, with 0 on the address being counted. The tmux monitor pane under-reports in the same way.
- **Fix:** count established sockets whose peer port is the configured NNTP port across every address. When the alternate provider uses the same port, assign sockets to it by matching `gethostbynamel()` of the alternate host, and count the rest as primary. `NNTPService::doConnect()` (`app/Services/NNTP/NNTPService.php:217`) calls the same method, so it under-counts too, although there it only decides whether to reuse an open connection.

### 28. The missed-`NZB` cleanup scans every release that already has an `NZB` file (Fixed)

- **Where:** `CollectionCleanupService::deleteCollectionsMissedAfterNzb()` (`app/Services/CollectionCleanupService.php`). Every per-group release job runs it through `deleteFinishedAndOrphans()`.
- **Bug:** the query joined `collections` to `releases` and filtered `r.nzbstatus = 1`. MariaDB started from the `nzbstatus` index, so it read every release with an NZB, looked up collections for each one, and then sorted the result.
- **Symptom:** with 846k releases on 2026-10-10, each call took about 4.9 seconds, even though only 67 collections had a `releases_id`. A release pass covers about 123 groups, so this cost about 10 minutes of database time per pass.
- **Fix (staged):** select from `collections` where `releases_id IS NOT NULL`, with an `EXISTS` check on the release's `nzbstatus`. The plan now reads the `ix_collection_releaseid` range and looks each release up by primary key, which takes milliseconds. A test in `tests/Feature/CbpCleanupServiceTest.php` checks which collections get deleted.

### 29. Header processing reads the collection regexes from Redis once per header (Fixed)

- **Where:** `RegexService::_fetchRegex()` (`app/Services/RegexService.php`), called from `tryRegex()` through `CollectionsCleaningService::collectionsCleaner()` for every header.
- **Bug:** the method stored each group's regular expression list in `$this->_regexCache` but never read it back, so every header cost a Redis round trip plus unserializing the whole list. The in-process check was dropped in 2022 (`e868008e0`) when the Redis cache was added.
- **Symptom:** an Excimer CPU profile of `articles:get-range binaries alt.binaries.frogs` over 20,000 articles on 2026-10-10 spent 32.7% of its PHP CPU in this call path.
- **Fix (staged):** each process reuses its copy of a group's list for 60 seconds before reading the shared cache again, so regular expression edits still reach long-running workers within about a minute of the 15-minute shared copy expiring. A failed fetch is not cached. Tests: `tests/Feature/RegexServiceFetchCacheTest.php` (the original code fails the two caching tests and passes the four behaviour tests).

### 30. Every header subject is checked against all ~80 mbstring encodings (Fixed)

- **Where:** `CollectionsCleaningService::normalizeString()` (`app/Services/CollectionsCleaningService.php`), reached through generic cleaning for subjects that no collection regular expression matched. Its output feeds the collection hash.
- **Bug:** `mb_convert_encoding($normalized, 'UTF-8', mb_list_encodings())` runs encoding detection across every encoding mbstring supports, about 16 µs per subject.
- **Symptom:** the same profile spent 16.0% of its PHP CPU in `normalizeString()`.
- **Trap:** the obvious fix (returning valid UTF-8 unchanged) would change collection hashes. Detection rewrites some valid input, such as UTF-7 look-alikes (`+ADw-`), emoji, NUL bytes, and even plain ASCII such as `!P` or even-length runs of one character, which are detected as UCS-2 or UCS-4.
- **Fix (staged):** detection still runs over every encoding, so the output is exactly the original call's, and the result is memoized per process (up to 4,096 subjects, then cleared). Every part of a post shares its collection name, so most calls are cache hits. An earlier version of this fix detected printable ASCII over just the 13 UTF/UCS encodings, which Codex showed is unsound: mbstring's choice depends on the whole candidate set, and `S/3/S*Z%7}` is UTF-8 over every encoding but UCS-2 over that subset, which would have split collections. The speed-up from the memo has not been re-measured on the live stack. Tests: `tests/Unit/CollectionsCleaningNormalizeStringTest.php` compares against the original call, including `S/3/S*Z%7}`.

### 31. Debian's tmux loses pane exit statuses and `pane-died` hooks (Mitigated)

- **Where:** tmux 3.3a (Debian bookworm, linked against libutempter) in both the app image and the build image. NNTmux reads the status in `TmuxPaneManager::paneSnapshot()` and waits on the hook in `TmuxPaneManager::waitForExit()`.
- **Bug:** when a pane's process closes its terminal, tmux calls `utempter_remove_record()` (`server-fn.c:321`), which sets SIGCHLD to the default disposition while it runs its helper. If the pane process's own SIGCHLD lands in that window, it is discarded. tmux re-raises SIGCHLD after `utempter_add_record()` (`spawn.c:461`) but not after the remove. The process stays an unreaped zombie, `pane_dead_status` and `pane_dead_signal` stay empty, and `pane-died` (which needs the status) never fires.
- **Symptom:** in a loop on 2026-10-10, 7 of 12 pane exits never got a status. `TmuxTaskRunner::beginCycle()` therefore missed failed workers, the monitor waited out its full `TMUX_MONITOR_DELAY` before reacting, and two `TmuxRuntimeTest` cases were flaky (`test_empty_available_queue_...`: null instead of exit code 0; `test_exit_hook_...`: a 3-second wait instead of under 2).
- **Fix (staged):** `paneSnapshot()` sends SIGCHLD to the tmux server, once per pane process, when a dead pane has neither an exit status nor a signal; tmux then reaps it and records the result. It only signals a process whose `/proc/<pid>/comm` starts with `tmux`. `waitForExit()` no longer relies on the hook alone (see #32). Tests: `tests/Feature/TmuxPaneReapTest.php` and two new `TmuxRuntimeTest` cases; `TmuxRuntimeTest` passed 15 of 15 runs afterwards.

### 32. A timed-out `tmux wait-for` swallows the next pane exit wake-up (Mitigated)

- **Where:** `TmuxPaneManager::waitForExit()` and tmux's `cmd_wait_for_signal()`/`cmd_wait_for_wait()` (`cmd-wait-for.c`).
- **Bug:** when the `wait-for` client is killed at the timeout, tmux keeps its waiter until the channel is next signalled. A later `wait-for -S` from the `pane-died` hook then wakes that stale waiter instead of marking the channel woken, so the next wait does not return.
- **Symptom:** a pane exit that happens while the monitor is between waits after a timed-out wait is not noticed until the following wait times out.
- **Fix (staged):** `waitForExit()` waits in 1-second slices and returns as soon as a pane that was alive at the start has died or disappeared. `pane_dead` comes from the terminal closing, so it does not depend on SIGCHLD or the hook. The hook still wakes the wait immediately when it works. An exit that happens before a wait starts still relies on the hook, as before.

### 33. The `Unit` test suite runs out of memory at PHP's default 128 MB limit (Open)

- **Where:** `vendor/bin/phpunit --testsuite=Unit` in `nntmux/build:local` (command-line `memory_limit` 128M).
- **Symptom:** `Allowed memory size of 134217728 bytes exhausted` during `Kernel::discoverCommands()` in a later test's `setUp()` (first seen in `MovieReverifyMatchesTest`), which aborts the run. The same happens without this session's changes. With `-d memory_limit=1G` the full Unit and Feature run passes.
- **Fix:** find what accumulates across tests (each `createApplication()` rediscovering console commands is a likely suspect), or set `memory_limit` in `phpunit.xml`.

### 34. IRC updates relabel a PRE's source to whoever repeated it (Fixed)

- **Where:** `IRCScraper::_updatePre()` (`app/Services/IRCScraper.php`), which always wrote `source = <announcing source>` when it updated an existing row.
- **Bug:** a later announcement of the same release overwrote the source of the ingester that stored it first. The synirc bot relays srrDB updates, so rows first stored from zenet or corrupt-net were relabelled `srrdb`.
- **Symptom:** on 2026-10-10, zenet and corrupt-net added about 20 pres between 00:43 and 00:47 UTC, but the database credited almost all of them to `srrdb`.
- **Fix (staged):** an update only sets `source` when the row has none, matching `PredbFeedImporter`.

## Packaging and deployment

### 11. `docker-compose.yml.prod-dist` starts a command that doesn't exist (Open)

- **Where:** `docker-compose.yml.prod-dist:33`: `php artisan tmux-ui:start & php artisan horizon`.
- **Bug:** there is no `tmux-ui:start` command; the real command is `tmux:start`.
- **Symptom:** the worker container never starts the indexer.

### 12. Production image ships `unrar-free`, which can't read RAR5 (Mitigated)

- **Where:** root `Dockerfile:9,30`.
- **Symptom:** archive inspection, password detection and NFO extraction fail on most current posts, which use RAR5.
- **Mitigation:** the docker/ overlay image swaps in Debian non-free `unrar` (6.21).

### 13. Native yEnc (`RapidYenc`) can never load in the `FrankenPHP` image (Fixed in the docker/ stack)

- **Where:** `NativePayloadDecoder::__construct()` (`app/Services/Yenc/NativePayloadDecoder.php`), the root `Dockerfile` (`dunglas/frankenphp`), and `config/yenc.php`.
- **Bug:** the decoder refused to load on thread-safe (ZTS) PHP, and the PHP inside FrankenPHP is ZTS. The image also had no FFI extension and no native library.
- **Symptom:** with `YENC_DECODER=auto`, workers log that native yEnc is unavailable and fall back to the PHP decoder.
- **Fix (staged):** a command-line PHP process runs a single thread even on a ZTS build, and FFI handles are cached per process, so the check now only requires command-line PHP on Linux with FFI. The docker/ app image installs `ffi` and builds `libnzbyenc.so` from `docker/app/nzb-yenc-ffi`: nzb-decode behind RapidYenc's C ABI, also proposed upstream as TheDancingDeveloper-org/nzb-decode#14. `docker/config/app.env.example` points `YENC_NATIVE_LIBRARY` at it. NNTmux's decoder parity suite passes against it on ZTS PHP: 96 tests, 9,012 assertions.

### 14. Unanchored `docker-compose.yml` rule in `.gitignore` (Fixed)

- **Bug:** the rule ignored every `docker-compose.yml` in the repository, including `docker/docker-compose.yml`.
- **Fix (staged):** added `!docker/docker-compose.yml`.

## Documentation

### 15. Stale `README` and wiki content (Open)

- **predb.ovh no longer resolves in DNS** (checked 2026-10-08), but the README (`README.md:346`) and the wiki recommend it. Live alternatives: predb.club (JSON and RSS), api.predb.net (JSON), and `predb.me` (RSS). These are now supported by `predb:import-feed`.
- **Env names in the README that the code doesn't read:**

  | README uses | Code reads |
  |---|---|
  | `SEARCH_ENGINE` | `SEARCH_DRIVER` |
  | `MANTICORE_HOST` | `MANTICORESEARCH_HOST` |
  | `TMDB_API_KEY` | `TMDB_APIKEY` |
  | `TRAKT_CLIENT_ID` | `TRAKTTV_APIKEY` |
  | `SCRAPE_IRC_CHANNELS` | nothing: channels are hard-coded in `config/irc_settings.php` |

- **Commands in the README that don't exist:** `nntmux:create-admin` (`README.md:312`), `nntmux:convert-collation` (`:205`), `nntmux:index-manticore`, `nntmux:reset-password` and `nntmux:test-nntp`, among others.
- **Wiki "Database Tuning"** lists `innodb_buffer_pool_instances`, which MariaDB removed in 10.6.
- **DOCKER.md** describes a GetPageSpeed nginx build with Brotli. Neither is in `docker/8.5/Dockerfile` or `docker/8.5/nginx.conf`.
- **MySQL 8 support:** the README claims support for "MySQL 8+" even though the schema dumps use the MariaDB-only collation `utf8mb4_uca1400_ai_ci`.

## Observed on the running stack (not yet root-caused)

### 16. Binary header storage hits lock-retry exhaustion under concurrent binaries and backfill

- **Log:** `production.ERROR: Binary header storage chunk rolled back {"groups_id":11,…,"attempts":10,"reason":"Lock retries exhausted"}`. Seen once about 25k groups were active with backfill on, even with `transaction-isolation = READ-COMMITTED`.
- **Status:** this matches the known contention described in the wiki (issue #1874 / PR #1872). Not seen since cutting back to the 207 groups.
- **Update (2026-10-10):** a related failure shows up with the 207 groups, 12 binaries threads, and 24 backfill threads. `Binary header storage chunk rolled back {"groups_id":1,…,"attempts":1,"reason":"Storage failed","exception":"Illuminate\\Database\\QueryException","code":"23000"}` was logged 85 times between 01:09 and 12:33 UTC, all in alt.binaries.boneless. A duplicate-key (23000) error is not retried, so the whole chunk is dropped and its articles fall back to part repair. Not root-caused; likely two workers inserting the same binary or part concurrently.

### 17. Compressed `XOVER` headers fail with the primary provider

- **Symptom:** with `NNTP_COMPRESSED_HEADERS=true` on Newshosting, binaries workers fail in `NNTPService::_getXFeatureTextResponse()` → `throwError()` (`NNTPService.php:892,960`).
- **Status:** the docker/ stack uses the upstream default (`false`). The failure could be provider-specific or a decode bug; not investigated further.

### 35. PreDB lookups break on release names with unbalanced parentheses (Open)

- **Where:** `ManticoreSearchDriver::searchPredb()` → `searchIndexes()` (`app/Services/Search/Drivers/ManticoreSearchDriver.php:1366-1371, ~1496`). The search string goes through `prepareUserSearchQuery()`, which keeps query operators for user-typed searches.
- **Bug:** the lookups search with machine-generated release names. A stray `)` or `(` reaches Manticore's `query_string` unescaped.
- **Symptom:** `ManticoreSearch searchIndexes ResponseException: "table predb_rt: query error: P08: syntax error, unexpected ')'"` with `"search":"idk_rza-everyone_knows_)-21ccf990"`; 6 times on 2026-10-09 and 5 on 2026-10-10. That release's PreDB lookup fails.
- **Fix:** escape machine-generated names with `escapeString()` in `searchPredb()`, or have `prepareUserSearchQuery()` escape unbalanced parentheses.

### 36. PHPStan can't analyse console commands without a database (Open)

- **Where:** `UpdatePostProcess::__construct()` (`app/Console/Commands/UpdatePostProcess.php:50`) injects `PostProcessService`. Building it builds `NameFixingService` → `ReleaseUpdateService` → `CategorizationService` → `CategorizationPipeline`, which checks the settings table through `ConfigurationProvider` (`ConfigurationProvider.php:132`).
- **Bug:** when it boots the app, Larastan resolves every console command. Without a reachable database, building `UpdatePostProcess` throws a `QueryException` (connection refused).
- **Symptom:** `Internal error: App\Console\Commands\UpdatePostProcess while analysing file …/ProcessReleasesCommand.php`, "Result is incomplete because of severe errors." This happens for any file under `app/Console/Commands`, so commands get no static analysis in CI or build containers.
- **Workaround:** run PHPStan with `DB_CONNECTION=sqlite DB_DATABASE=:memory:`.
- **Fix:** resolve `PostProcessService` lazily in `handle()`, or make its settings load lazily.

### 37. Release reconcile deadlocked with header storage on busy groups (Fixed)

- **Where:** `ReleaseProcessingService::reconcileCollectionIds()`. It recomputed binary and collection aggregates for up to 500 collections in one transaction, using `UPDATE binaries b LEFT JOIN (SELECT … FROM parts …)`. Under READ-COMMITTED, MariaDB still locks every `parts` row an UPDATE reads (22,765 row locks for one live batch of 717 binaries). Meanwhile `HeaderStorageService` updates the same binaries and collections inside its own transactions.
- **Symptom:** frequent InnoDB deadlocks in alt.binaries.boneless (group 1). From 11:40 to 13:37 UTC on 2026-10-10:
  - 386 header chunks (117k articles) were rolled back with `"reason":"Lock retries exhausted","code":"40001"`;
  - the `releases` pane failed 38 times in `processIncompleteCollections()`.
- **Fix:** read the aggregates with plain SELECTs, which take no locks, and write only rows that drifted, each by primary key and only if it still holds the values read. A batch with nothing to correct now issues no writes at all, taking 24 ms for 500 live collections. One code path now serves MariaDB and SQLite. Covered by `CbpReleaseEligibilityTest`, which also runs on MariaDB through `CbpReleaseEligibilityMariaDbTest`.
- **Made worse by:** `collection_delay_hours = 0`, which made every collection old enough to reconcile and release as it is, so the reconcile swept collections header workers were still writing. `tuning.sql` now sets 2, the app's own default.

### 39. One dead address in a round-robin IRC host kept the EFnet scraper offline (Fixed)

- **Where:** `IRCClient::_initiateStream()` (`app/Services/IRCClient.php`).
- **Symptom:** `irc.efnet.org` resolves to ten servers. One of them (195.159.90.90) did not answer. PHP's `stream_socket_client()` tries a single resolved address, so the scraper failed with "Unable to connect" every time the dead one came first. The supervisor kept backing off, up to its maximum delay, while the other nine servers were reachable.
- **Fix:** after the first attempt fails, the client resolves every address of the host and tries each in random order. A TLS connection by address still checks the certificate against the host name.

## Issues in the docker/ stack itself (all fixed)

| Issue | Fix |
|---|---|
| Manticore restart loop: the image entrypoint `chown`s its config file, which fails on a read-only mount | Mounted the config read/write |
| tmux panes died with "This account is currently not available": `www-data`'s login shell is `nologin` | Set `SHELL=/bin/bash` for the app services |
| Grafana showed its own login page because Caddy's default directive order ran `request_header -X-JWT-Assertion` after `forward_auth`, deleting the JWT `forward_auth` had just added | Wrapped the handler in `route { }` |
| Bulk-deleting groups while the indexer was running left 7,072 collections behind | Second cleanup pass. Prefer pausing the indexer (`make -C docker down` or `restart`) before large deletes |
| `make backup` reported success and rotated out good backups when `mariadb-dump` failed (no `pipefail`); dumps were world-readable under umask 022 | `pipefail`, write to a `.partial` file, publish and prune only on success; backups dir `0700`, files `0600` |
| `make init` stopped before `docker compose up -d` whenever every public PreDB feed was down | The PreDB seed is best effort (the scheduler polls every 5 minutes) |
| `APP_URL` and Grafana's root URL were pinned to `localhost:8080`, so a changed `APP_PORT` or LAN access produced wrong absolute links and Grafana redirects | `APP_URL` in `docker/.env` (blank = `http://localhost:$APP_PORT`); `generate-env` syncs it into `config/app.env`, and `GF_SERVER_ROOT_URL` uses it |
| `generate-env` exited on "Unresolved placeholders" when `config/app.env` had NNTP host/user/password but no port, SSL, or connection count | Missing NNTP settings fall back to the same defaults as the JSON path |
| On native Linux, app containers (UID 33) couldn't write `data/storage` or read the `0600` `config/app.env` created by the host user | `make perms` (run by `make env`) chowns the app mounts to 33 and gives group 33 read on `config/app.env`; a no-op on macOS |
| Compose's `MARIADB_MEMORY` fallback (10g) disagreed with `docker/.env.example` and the tuning comment (13g) | Fallback raised to 13g |
