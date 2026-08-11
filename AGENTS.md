# AGENTS.md

> AI coding agent guidelines for NNTmux - a Laravel 13 Usenet indexer.

## Development workflow

Master only moves by pull request (server-enforced — direct pushes are rejected, even for docs-only changes). Every change: branch in a worktree → PR with auto-merge armed → monitor until merged → clean up and sync local master. See `docs/agents/development-workflow.md` before committing anything.

## Agent skills

### Issue tracker

Issues are tracked in GitHub Issues. See `docs/agents/issue-tracker.md`.

### Triage labels

Triage uses the five default canonical labels. See `docs/agents/triage-labels.md`.

### Domain docs

Domain documentation uses the single-context layout. See `docs/agents/domain.md`.

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
| **Passkey** | `app/Actions/Passkeys/`, `app/Http/Controllers/Auth/Passkey*` | Spatie Laravel Passkeys; ceremony actions (`GeneratePasskeyRegisterOptionsAction`, `FindPasskeyToAuthenticateAction`) wire into routes `passkeys.*` in `routes/web.php`. `GeneratePasskeyRegisterOptionsAction` overrides `authenticatorSelection()` (defaults: attachment=null, `residentKey=preferred`, `userVerification=preferred`) and injects WebAuthn L3 `hints` + `credProps` extension so Windows Hello / Touch ID / phone-via-QR / FIDO2 keys all appear in the browser picker on Windows domain machines. Tunable via `PASSKEY_AUTHENTICATOR_ATTACHMENT`, `PASSKEY_RESIDENT_KEY`, `PASSKEY_USER_VERIFICATION`, `PASSKEY_RELYING_PARTY_ID` (see `config/passkeys.php`) |

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
- **Whenever you add or rename an `env()` key — in any file, whether under `config/*.php` or anywhere else in the codebase — you MUST also add it (with a sensible default and a short comment) to `.env.example`.** Treat any new env setting without the matching `.env.example` entry as an incomplete task.
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

Auto-runs: PHP lint, Composer lock validation, Pint formatting, and design-system checks (`scripts/check-design-system.sh`, when frontend files changed). Commit limits: 200 char subject, 72 char body.

When completing a task, stage newly created project files with Git. Do not stage temporary files or planning documents.

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

- **Livewire 3**: Used only by the forum package (`resources/forum/livewire-tailwind/`) and the vendored Spatie Pulse dashboard views (`resources/views/vendor/pulse/`). All application pages (auth, profile, admin, browse, etc.) are plain Blade + Alpine.
- **Alpine.js**: CSP-safe build with component architecture in `resources/js/alpine/`
  - Core components loaded eagerly in `alpine/index.js`
  - Page-specific components lazy-loaded via `alpine/lazy-loader.js`
  - Lazy-loaded pages must declare an `x-data` name that matches a key in `alpine/lazy-loader.js`, or the component JS will never load; example: `resources/views/admin/content/index.blade.php` uses `x-data="contentToggle"` so delete/toggle handlers from `resources/js/alpine/components/content-toggle.js` are available
  - Stores in `alpine/stores/`, components in `alpine/components/`
- **CSS**: Main entry is `resources/css/app.css` (imports `csp-safe.css` for component styles)
- **Vite entry points**: `resources/js/app.js`, `resources/css/app.css`, `resources/forum/blade-tailwind/js/forum.js`, `resources/forum/blade-tailwind/css/forum.css`

This structure ensures Content Security Policy (CSP) compliance by using Alpine.js CSP-safe build and keeping scripts and styles in external files.

### Design system

`resources/css/app.css` defines the styling foundation: three color schemes (`data-color-scheme="blue|emerald|violet"` on `<html>`) providing surface variables (`--surface-body`, `--surface-card`, `--surface-panel-alt`, `--border-default`, `--text-muted`, ...) and a `--color-primary-50…950` accent ramp. Dark mode is class-based (`.dark` on the root). `scripts/check-design-system.sh` enforces the mechanical rules below on pre-commit.

**Color rules**

- Accents (links, primary actions, active states, focus rings, selected tabs) use `primary-*` utilities, **never** `blue-*`/`indigo-*` or another palette color — the emerald and violet schemes only retheme token-driven classes. When unsure, prefer `primary-*`: under the default blue scheme it renders identically.
- Genuine status colors stay literal: success green, danger red, warning yellow, info cyan.
- Surfaces/containers use the semantic classes `.card`, `.surface-panel`, `.surface-panel-alt`, `.auth-card` (or the `--surface-*` variables), not hardcoded `bg-white`/`bg-gray-*`.
- Every color utility carries a `dark:` variant (or is inherited from a token-driven ancestor). There is no global dark-mode rescue CSS — correctness lives at the source.

**Components** (in `resources/views/components/`)

- Buttons: `<x-button>` / `<x-button-link>` — variants `primary|secondary|muted|success|danger|warning|ghost`, sizes `sm|md|lg|icon`, `icon` prop for a leading Font Awesome icon. Extra classes/attributes pass through; escape Alpine/Vue bindings on component tags as `::disabled` etc. so Blade doesn't eval them. Forum app-level views use `<x-forum.button>`/`<x-forum.button-link>`/`<x-forum.button-secondary>`. Compact release-row actions may use the semantic `release-action*` classes.
- Forms: `<x-input>`, `<x-select>`, `<x-textarea>`, `<x-label>`; other primitives: `<x-badge>`, `<x-panel>`, `<x-page-header>`, `<x-breadcrumb>`, `<x-empty-state>`, `<x-sort-dropdown>`, `<x-view-toggle>`.
- Legitimately bespoke (don't force into components): nav/dropdown togglers, modal close-X icons, state-conditional toggle chips/tabs, pagination, input-group-attached addons.
- Icons: Font Awesome only (`fas`/`far`/`fab`); no feather-icons in app views (the out-of-scope forum package theme still bundles it — leave that alone).

**Hard rules**

- No inline `style=` attributes in views: use Tailwind utilities or a class in `csp-safe.css`; dynamic widths use the `progress-bar` class + `data-width` attribute (animated globally by `resources/js/progress-bar.js`). Documented exception: DB-driven forum category colors.
- No new `!important` in `app.css` — its custom rules are unlayered, so under Tailwind v4 cascade layers they already beat `@layer utilities`. Budget is 1 (the `[x-cloak]` rule).
- **Trap:** the live forum frontend renders the package preset in `resources/forum/blade-tailwind/` (view namespace `forum::`); `resources/views/forum/` is an orphaned copy nothing renders. Keep the forum bundle in the Vite entry points and `feather-icons` in `package.json` — the preset's `forum.js` imports it.
- Email views (`resources/views/emails`, `components/mail`, `vendor/mail`) cannot use the app stylesheet — inline styles there are expected.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- On this host, invoke every Sail command with the `sudo vendor/bin/sail` prefix; this applies to every Sail example below.
- In agent workspaces without a root `docker-compose.yml`, run Sail with `SAIL_FILES=.github/docker-compose.ci.yml APP_SERVICE=laravel.test`.
- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `vendor/bin/sail artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `vendor/bin/sail artisan test --compact`.
- To run all tests in a file: `vendor/bin/sail artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `vendor/bin/sail artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== revolution/laravel-boost-phpstorm-copilot/core rules ===

## PhpStorm with GitHub Copilot Plugin

This package provides custom CodeEnvironment integration for PhpStorm with GitHub Copilot plugin with Laravel Boost. It enables PhpStorm users to leverage Laravel Boost's MCP (Model Context Protocol) server functionality.

### Important: Project Path Verification

**Before using Laravel Boost MCP tools in PhpStorm with GitHub Copilot plugin, verify that the project path in the global MCP configuration file matches your current project.**

The MCP configuration file is stored system-wide at:
- macOS, Linux: `~/.config/github-copilot/intellij/mcp.json`
- Windows: `%LOCALAPPDATA%\github-copilot\intellij\mcp.json`

If the project path in the MCP configuration does not match your current Laravel project, **you must update it before using MCP tools**:

<code-snippet name="Update MCP Configuration for Current Project" lang="bash">
php artisan boost:install --guidelines --skills --mcp --no-interaction
</code-snippet>

This command updates the MCP configuration file with the absolute path to your current Laravel project, ensuring MCP tools interact with the correct project.

### When to Run boost:install

Run `php artisan boost:install` whenever you:
- Switch to a different Laravel project
- Clone or move your project to a new location
- Notice MCP tools are accessing the wrong project's data

### Why This is Necessary

Unlike project-local MCP configurations, PhpStorm with GitHub Copilot plugin stores MCP server configurations in a system-wide location. This allows multiple projects to share the same MCP server registration, but requires updating the configuration when switching between projects to ensure the correct project path is used.

</laravel-boost-guidelines>
