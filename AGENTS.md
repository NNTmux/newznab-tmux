# AGENTS.md

> AI coding agent guidelines for NNTmux - a Laravel 12 Usenet indexer.

## Quick Reference

```bash
php artisan test --compact --filter=TestName  # Run single test (PHPUnit only)
./vendor/bin/pint --dirty                     # Format changed files
php artisan tmux:start                        # Start processing engine
npm run build                                 # Required after frontend changes
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
| **Pipeline** | `*/Pipes/` | `TvProcessingPipeline` (TMDB→TVDB→TVMaze→Trakt), `CategorizationPipeline` (TV→Movie→PC→Console→Music→Book→XXX→Misc) |
| **Driver** | `Search/Drivers/` | Manticore/Elasticsearch via `SEARCH_DRIVER` env var |
| **Runners** | `Runners/` | `BinariesRunner`, `ReleasesRunner`, `BackfillRunner`, `PostProcessRunner` |
| **DTO** | `*/DTO/`, `app/Support/DTOs/` | `NameFixResult`, `ReleaseProcessingContext`, `ReleaseCreationResult` |
| **Enum** | `app/Enums/` | `UserRole`, `QueueType`, `FileCompletionStatus` |

## Tmux Processing Engine

Multi-pane terminal orchestrator at `app/Services/Tmux/`. Components: `TmuxSessionManager`, `TmuxLayoutBuilder`, `TmuxPaneManager`, `TmuxTaskRunner`, `TmuxMonitorService`.

**Sequential Modes** (`Settings::settingValue('sequential')`):
- Mode 0: Full (3 windows, parallel panes)
- Mode 1: Basic (reduced)
- Mode 2: Stripped (minimal)

**Commands**: `tmux:start`, `tmux:stop`, `tmux:attach`, `tmux:monitor`, `tmux:health-check`

**Config**: `config/tmux.php` + database `settings` table

## Testing

PHPUnit only (no Pest). Create tests: `php artisan make:test --phpunit {name}`

- In-memory SQLite (`DB_CONNECTION=testing`)
- App boot can hit `Settings::settingValue()` via `CategorizationPipeline` (`app/Providers/CategorizationServiceProvider.php` → `app/Services/Categorization/CategorizationPipeline.php`), even in focused controller tests
- For isolated tests that bypass the normal app test DB setup, create a minimal `settings` table/rows first; `categorizeforeign` and `catwebdl` are the minimum keys needed for this bootstrap path
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

### Config
- App configs: `config/nntmux*.php`, `config/tmux.php`, `config/search.php`
- Never `env()` outside config - use `config('key')`
- Runtime settings: `Settings::settingValue()`

### Commands
- 80+ auto-registered in `app/Console/Commands/`
- Create with `php artisan make:` + `--no-interaction`

## Code Formatting

**Always run Pint after completing any PHP code changes:**

```bash
./vendor/bin/pint --dirty  # Format only changed files
```

This is mandatory — run it before considering any task done. Do not wait for the pre-commit hook to catch formatting issues.

## Pre-commit (CaptainHook)

Auto-runs: PHP lint, Composer lock validation, Pint formatting. Commit limits: 200 char subject, 72 char body.

## Key Directories

| Path | Purpose |
|------|---------|
| `app/Services/TvProcessing/` | TV metadata pipeline |
| `app/Services/Search/` | Manticore/ES abstraction |
| `app/Services/NameFixing/` | Release name correction (see README.md there) |
| `app/Services/Tmux/` | Tmux orchestration |
| `app/Facades/` | Static service accessors |

## External APIs

Requires `.env` keys: TMDB, TVDB, TVMaze, Trakt, OMDB (TV/Movies); IGDB, GiantBomb, Steam (Games); AniList, AniDB (Anime); NNTP credentials.

## Frontend

Blade + TailwindCSS v4 + Vite bundling. Run `npm run build` after changes.

- **Livewire 3**: Used only in the forum package
- **Alpine.js**: CSP-safe build with component architecture in `resources/js/alpine/`
  - Core components loaded eagerly in `alpine/index.js`
  - Page-specific components lazy-loaded via `alpine/lazy-loader.js`
  - Stores in `alpine/stores/`, components in `alpine/components/`
- **CSS**: Main entry is `resources/css/app.css` (imports `csp-safe.css` for component styles)
- **Vite entry points**: `resources/js/app.js`, `resources/css/app.css`, plus forum assets

This structure ensures Content Security Policy (CSP) compliance by using Alpine.js CSP-safe build and keeping scripts and styles in external files.
