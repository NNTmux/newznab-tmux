# AGENTS.md

> AI coding agent guidelines for NNTmux - a Laravel 13 Usenet indexer.

## Quick Reference

```bash
php artisan test --compact --filter=TestName  # Run single test (PHPUnit only)
./vendor/bin/pint --dirty                     # Format changed files
php artisan tmux:start                        # Start processing engine
npm run build                                 # Required after frontend changes
php artisan route:cache                       # Refresh cached routes if new routes seem missing
```

## Architecture

NNTmux scans Usenet servers, collects headers, organizes releases, and enriches with metadata. Data flow:

```
NNTP → NNTPService → BinariesRunner → ReleaseCreationService → ReleaseProcessingService → SearchService → API/Web
```

### Key Patterns

| Pattern | Location | Example |
|---------|----------|---------|
| **Service Layer** | `app/Services/` | 50+ services with facades (`Search::`, `Categorization::`, `TvProcessing::`, `Yenc::`, `Elasticsearch::`) |
| **Pipeline** | `*/Pipes/` | `TvProcessingPipeline` (TMDB→TVDB→TVMaze→Trakt), `CategorizationPipeline` (priority-driven; `Music` runs before `Book` for audiobook detection) |
| **Driver** | `Search/Drivers/` | Manticore/Elasticsearch via `SEARCH_DRIVER` env var |
| **Runners** | `Runners/` | `BinariesRunner`, `ReleasesRunner`, `BackfillRunner`, `PostProcessRunner` |
| **DTO** | `*/DTO/`, `app/Support/DTOs/`, `app/Data/` | Internal: `NameFixResult`, `ReleaseProcessingContext`, `ReleaseCreationResult`. API responses use Spatie Laravel Data in `app/Data/Api/` (`ReleaseData`, `CategoryData`, `DetailsData`) |
| **Enum** | `app/Enums/` | `UserRole`, `QueueType`, `FileCompletionStatus`, `SecondarySearchIndex`, `NzbImportStatus` |
| **Observer** | `app/Observers/`, `AppServiceProvider` | `ReleaseObserver`, `MovieInfoObserver`, `RolePromotionObserver` |
| **View Composer** | `app/View/Composers/`, `AppServiceProvider` | `GlobalDataComposer` shared across `layouts.*` and `admin.*` |
| **Status Probe** | `app/Services/StatusProbes/` | `ServiceProbeRegistry` aggregates `DatabaseProbe`, `DiskProbe`, `NntpProbe`, `QueueProbe`, `RedisProbe`, `SearchProbe` for `StatusPageController` (`/status`) and `DegradeWhenRedisUnreachable` middleware; tune via `config/status-probes.php` |
| **Passkey** | `app/Actions/Passkeys/`, `app/Http/Controllers/Auth/Passkey*` | Spatie Laravel Passkeys; ceremony actions (`GeneratePasskeyRegisterOptionsAction`, `FindPasskeyToAuthenticateAction`) wire into routes `passkeys.*` in `routes/web.php` |

## Tmux Processing Engine

Multi-pane terminal orchestrator at `app/Services/Tmux/`. Components: `TmuxSessionManager`, `TmuxLayoutBuilder`, `TmuxPaneManager`, `TmuxTaskRunner`, `TmuxMonitorService`.

**Sequential Modes** (`Settings::settingValue('sequential')`):
- Mode 0: Full (3 windows, parallel panes)
- Mode 1: Basic (reduced)
- Mode 2: Stripped (minimal)

**Commands**: `tmux:start`, `tmux:stop`, `tmux:attach`, `tmux:monitor`, `tmux:health-check`

**Config**: `config/tmux.php` + database `settings` table

**Post-process panes** (window 2: panes 2.0–2.3) run `php artisan multiprocessing:postprocess <type>`, which fans out work as multiple `postprocess:guid <type> <char>` child processes via `App\Services\Runners\PostProcessRunner`. Types: `add`/`nfo` (pane 2.0), `tv`/`ani` (2.1), `ama` (2.2 — books+music+console+games), `mov` (2.3). Per-type aliases: `boo`, `mus`, `con`, `gam`.

- **Live tmux output**: set `STREAM_FORK_OUTPUT=true` in `.env` (`config('nntmux.stream_fork_output')`). When false (default), child output is buffered per batch and the pane may look idle until a batch completes.
- **Parallelism settings** (all default to `1` in `database/seeders/SettingsTableSeeder.php`; raise via Admin UI or DB): `postthreads` (additional), `nfothreads` (NFO when `post=3`), `postthreadsnon` (TV/anime/movies), `postthreadsamazon` (books/music/console/games and `ama` fan-out). Raising `nfothreads` opens that many parallel NNTP sessions for NFO children.
- **Batch sizing**: up to 16 distinct first-character GUID buckets per type per cycle (`LIMIT 16` in `PostProcessRunner`); each bucket processes its slice sequentially inside `postprocess:guid`. Additional processing also respects `maxaddprocessed` (default 25) per bucket.
- **Direct CLI**: `update:postprocess <type>` remains available for single-process runs outside tmux; tmux panes use the multiprocessing command only.

## Testing

PHPUnit only (no Pest). Create tests: `php artisan make:test --phpunit {name}`

- In-memory SQLite (`DB_CONNECTION=testing`)
- App boot can hit `Settings::settingValue()` via `CategorizationPipeline` (`app/Providers/CategorizationServiceProvider.php` → `app/Services/Categorization/CategorizationPipeline.php`), even in focused controller tests
- For isolated tests that bypass the normal app test DB setup, seed a minimal `settings` table before app bootstrap; `categorizeforeign` and `catwebdl` are the minimum keys needed for this path, and `tests/Feature/AdminContentControllerTest.php` shows the file-backed SQLite workaround when `php artisan test` would otherwise fail during startup
- Feature tests that render shared layouts or admin pages may need to clear `App\View\Composers\GlobalDataComposer::$resolvedData`; see `resetGlobalComposerState()` helpers in `tests/Feature/AdminContentControllerTest.php`, `AdminGroupControllerTest.php`, and `NzbAndRssAccessTest.php`
- All HTTP mocked - no real API calls
- Suites: `Install`, `Unit`, `Feature` (also `tests/Integration/` for live API tests, not in CI)
- Use model factories; check for custom states first
- Mocks in `tests/Fixtures/`, `tests/mock_data/`
- Test harnesses in `tests/Support/` (e.g., `DatabaseTestCase`, `TestBinariesHarness`)
- PHPUnit 12 — use `#[Test]` attributes or `test` prefix naming

## Project Conventions

### Models (`app/Models/`)
- Casts in `casts()` method, not `$casts` property
- Foreign keys: `{table}_id` (e.g., `groups_id`)
- Key: `Release`, `Video`, `TvEpisode`, `MovieInfo`, `UsenetGroup`

### API (`app/Http/Controllers/Api/`)
- v1: XML (newznab compat) - `ApiController.php`
- v2: JSON REST - `ApiV2Controller.php`
- RSS feeds are separate from `/api`: edit `routes/rss.php` + `App\Http\Controllers\RssController`; `/rss/*` is mounted from `bootstrap/app.php` and `RssController::userCheck()` validates `api_token`

### Config
- App configs: `config/nntmux*.php`, `config/tmux.php`, `config/search.php`
- Never `env()` outside config - use `config('key')`
- Runtime settings: `Settings::settingValue()`
- Laravel 13 route/middleware wiring lives in `bootstrap/app.php`; use that file when adding route groups, aliases, or middleware (for example the `/rss` mount)
- Custom global middleware in `app/Http/Middleware/`: `DegradeWhenRedisUnreachable` (prepended; short-circuits requests when Redis is down via `StatusProbes`), `BlockAbusiveServices` (blocks AIOStreams, Oracle Cloud, UsenetStreamer, Cloudflare WARP), `NoCacheForAuthenticatedUsers` (CDN cache busting), `ContentSecurityPolicy`, `EnforceSessionToken`, `TrustedDevice2FAMiddleware`
- In Docker/Sail, `Makefile` exports `.env` `SEARCH_DRIVER` as `COMPOSE_PROFILES`, so only the matching Manticore/Elasticsearch service starts

### Manticore `releases_rt` signed columns

- In Manticore, `integer` is **unsigned 32-bit**; negative DB values (e.g. `passwordstatus = -1`, `haspreview = -1`) are stored as large positives (e.g. `4294967295`), so `passwordstatus <= 1` filters never match. The `releases_rt` schema uses **`bigint`** for `passwordstatus` and `haspreview` so values stay signed.
- Changing column types requires dropping and recreating the RT table(s); Manticore cannot `ALTER` attribute types in place. **`php artisan manticore:create-indexes --drop`** drops and recreates **every** index defined in [`app/Console/Commands/CreateManticoreIndexes.php`](app/Console/Commands/CreateManticoreIndexes.php) (releases, predb, movies, tvshows, secondaries, etc.) as empty shells. Then repopulate what you use, e.g. **`php artisan nntmux:populate --manticore --all`** or at minimum **`--releases`** (and other index flags as needed). Prefer a maintenance window: run **`php artisan tmux:stop`** (and pause queue workers that touch search) during drop/repopulate, then **`php artisan tmux:start`**. Optionally enable MySQL search fallback via `nntmux.mysql_search_fallback` while indexes are empty.

### Commands
- 80+ auto-registered in `app/Console/Commands/`
- Create with `php artisan make:` + `--no-interaction`
- Docker/Sail convenience targets live in `Makefile`; prefer `make artisan cmd="..."`, `make test filter=TestName`, `make pint`, and `make npm-build` when working inside containers
- This workspace may have cached routes under `bootstrap/cache/routes-*.php`; after adding/changing routes, refresh with `php artisan route:cache` if a route appears missing

### Admin Content
- Admin content ordering is scoped by `contenttype`, not global: Homepage rows only reorder Homepage rows, Useful Links only reorder Useful Links
- The admin list at `resources/views/admin/content/index.blade.php` renders one draggable table per content group and uses Alpine component `contentToggle`
- `resources/js/alpine/components/content-toggle.js` is the integration point for admin content interactions: grouped drag ordering, enable/disable toggles, and delete confirmations all live there
- Reorder requests go to `AdminContentController::reorder()` and must include the exact ID set for one `contenttype`; mixed-type or partial payloads are rejected
- The ordinal field is intentionally hidden on `resources/views/admin/content/add.blade.php`; the server assigns new items to the bottom of their own group in `AdminContentController::nextBottomOrdinal()`
- Deleting content does not renumber remaining items; gaps in per-group ordinals are expected

## Code Formatting & Quality

**After every code change, run all of the following before considering a task done:**

### 1. Apply style fixes to changed PHP files
```bash
./vendor/bin/pint --dirty  # Format only changed files
```

> If Pint changes files, keep those changes and rerun Pint until it reports clean output.

### 2. Check for static analysis errors
```bash
./vendor/bin/phpstan analyse --memory-limit=2G  # Run PHPStan static analysis
```

### 3. Check for syntax / lint errors
```bash
find app -name "*.php" | xargs php -l  # PHP syntax lint on all changed files
```

> **These steps are mandatory.** Run them before considering any task done. Do not wait for the pre-commit hook to catch formatting or type errors. If PHPStan reports new errors introduced by your changes, fix them before finishing. If you add a PHPStan baseline entry, document why.

## Pre-commit (CaptainHook)

Auto-runs: PHP lint, Composer lock validation, Pint formatting. Commit limits: 200 char subject, 72 char body.

## Key Directories

| Path | Purpose |
|------|---------|
| `app/Services/TvProcessing/` | TV metadata pipeline |
| `app/Services/Search/` | Manticore/ES abstraction |
| `app/Services/NameFixing/` | Release name correction (see README.md there) |
| `app/Services/Tmux/` | Tmux orchestration |
| `app/Services/StatusProbes/` | Service health probes feeding `/status` and degrade middleware |
| `app/Facades/` | Static service accessors |

## External APIs

Requires `.env` keys: TMDB, TVDB, TVMaze, Trakt, OMDB (TV/Movies); IGDB, GiantBomb, Steam (Games); AniList, AniDB (Anime); NNTP credentials.

## Frontend

Blade + TailwindCSS v4 + Vite bundling. Run `npm run build` after changes.

- **Livewire 3**: Used by the forum package, the Spatie Pulse dashboard (`resources/views/vendor/pulse/`), and the auth login form (`app/Livewire/Forms/LoginForm.php`, `app/Livewire/Actions/Logout.php`). Most pages remain plain Blade + Alpine.
- **Alpine.js**: CSP-safe build with component architecture in `resources/js/alpine/`
  - Core components loaded eagerly in `alpine/index.js`
  - Page-specific components lazy-loaded via `alpine/lazy-loader.js`
  - Lazy-loaded pages must declare an `x-data` name that matches a key in `alpine/lazy-loader.js`, or the component JS will never load; example: `resources/views/admin/content/index.blade.php` uses `x-data="contentToggle"` so delete/toggle handlers from `resources/js/alpine/components/content-toggle.js` are available
  - Stores in `alpine/stores/`, components in `alpine/components/`
- **CSS**: Main entry is `resources/css/app.css` (imports `csp-safe.css` for component styles)
- **Vite entry points**: `resources/js/app.js`, `resources/css/app.css`, `resources/forum/blade-tailwind/js/forum.js`, `resources/forum/blade-tailwind/css/forum.css`

This structure ensures Content Security Policy (CSP) compliance by using Alpine.js CSP-safe build and keeping scripts and styles in external files.
