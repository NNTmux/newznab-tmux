# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

NNTmux (newznab-tmux) is a modern Usenet indexer built on Laravel 12 with PHP 8.4. It scans Usenet servers, collects article headers, organizes them into searchable releases, and enriches them with metadata from external APIs (TMDB, TVDB, TVMaze, OMDB, IGDB).

## Common Commands

### Development
```bash
composer install && npm install    # Install dependencies
npm run dev                        # Vite dev server with HMR
php artisan serve                  # Local PHP server
npm run build                      # Production build
```

### Testing
```bash
php artisan test                              # Run all tests
php artisan test --testsuite=Unit             # Unit tests only
php artisan test --testsuite=Feature          # Feature tests only
php artisan test --filter=TestClassName       # Single test class
php artisan test --filter=test_method_name    # Single test method
php artisan test --coverage                   # With coverage (requires Xdebug)
```

### Code Quality
```bash
./vendor/bin/pint                  # Laravel Pint formatter (PSR-12)
./vendor/bin/phpstan analyse       # Static analysis (via larastan)
npm run format                     # Prettier for JS/CSS/Vue
```

### Database
```bash
php artisan migrate                # Run migrations
php artisan migrate:fresh --seed   # Reset and seed
```

### Tmux Processing Engine
```bash
php artisan tmux:start             # Start processing session
php artisan tmux:stop              # Stop session
php artisan tmux:attach            # Attach to session
php artisan tmux:health-check      # Monitor health
```

### Search Indexing
```bash
php artisan nntmux:create-manticore-indexes   # Create Manticore indexes
php artisan nntmux:populate-search-indexes    # Populate indexes
php artisan nntmux:create-es-indexes          # Elasticsearch alternative
```

### Cache Management
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

## Architecture Overview

### Service Layer Pattern
Major features are encapsulated in dedicated service classes under `app/Services/`:
- **SearchService** - Manager pattern with Manticore/Elasticsearch drivers
- **NNTPService** - NNTP protocol handling (53KB, highly specialized)
- **TmuxService** - Terminal multiplexer orchestration
- **ReleaseProcessingService** - Release creation and enhancement

### Pipeline Pattern
Complex processing uses Laravel Pipelines (`app/Services/Pipelines/`):
- **TvProcessingPipeline** - TV metadata with provider fallback chain (TMDB → TVDB → TVMaze → Trakt)
- **CategorizationPipeline** - Multi-stage release categorization
- **AdultProcessingPipeline** - Adult content handling with age verification

### Facades
Static access to services via `app/Facades/`:
- `Search::searchReleases()`, `Search::autocomplete()`
- `Categorization::categorize()`
- `TvProcessing::processRelease()`

### Data Flow
```
NNTP Server → NNTPService → BinariesRunner → ReleaseCreationService
    → ReleaseProcessingService (name fixing, categorization, metadata)
    → SearchService (index) → API/Web Controllers → User
```

### Key Directories
- `app/Console/Commands/` - 80+ Artisan commands
- `app/Http/Controllers/Api/` - API v1 (XML) and v2 (REST) endpoints
- `app/Models/` - 60+ Eloquent models
- `app/Services/Tmux/` - Tmux orchestration (panes, sessions, monitoring)
- `app/Services/Search/` - Dual search engine abstraction
- `app/Services/Runners/` - Background task runners (releases, binaries, backfill)

### Configuration
Custom configs beyond Laravel defaults in `config/`:
- `tmux.php` - Tmux session settings (colors, fonts, monitoring)
- `search.php`, `manticoresearch.php`, `elasticsearch.php` - Search engines
- `nntmux.php`, `nntmux_nntp.php` - Core app and NNTP settings
- `irc_settings.php` - IRC PRE scraper config

## Pre-commit Hooks

CaptainHook runs on commit:
- PHP linting
- Composer lock file validation
- Laravel Pint formatting

Commit messages: max 200 char subject, 72 char body lines.

## Testing Notes

- Tests use in-memory SQLite (`DB_CONNECTION=testing`)
- HTTP calls are mocked (no real API calls in tests)
- Test suites: `Install`, `Unit`, `Feature`
- Manual test script: `php tests/manual_test_anilist.php`

## External Dependencies

Key integrations requiring API keys (configured in `.env`):
- TMDB, TVDB, TVMaze, Trakt, OMDB - Movie/TV metadata
- IGDB, GiantBomb - Game metadata
- AniList/AniDB - Anime metadata
- NNTP provider credentials for Usenet access

## Frontend Stack

- Blade templates + TailwindCSS v4 for main application
- Livewire 3 for forum functionality
- Vite for asset bundling
- Alpine.js CSP safe build for interactivity
