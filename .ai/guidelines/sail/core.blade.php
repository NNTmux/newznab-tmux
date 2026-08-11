@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Laravel Sail (project override)

> **This guideline OVERRULES Boost's built-in Sail rules.** Do not require `{{ $assist->sailBinaryPath() }}` for day-to-day PHP, Artisan, Composer, Node, Pint, PHPStan, or tests.

## Default: run on the WSL host

Prefer host commands (as in the Quick Reference at the top of `AGENTS.md`):

- Artisan: `{{ $assist->artisanCommand('[command]') }}` (e.g. `php artisan test --compact --filter=TestName`)
- Composer: `{{ $assist->composerCommand('install') }}`
- Node/npm: `{{ $assist->nodePackageManagerCommand('run build') }}`
- Vendor bins: `{{ $assist->binCommand('pint --dirty') }}`, `{{ $assist->binCommand('phpstan analyse --memory-limit=2G') }}`

Do **not** prefix every command with Sail. Ignore any conflicting Boost text that says you MUST execute all commands through Sail.

## When Sail / Docker is appropriate

Use Sail or `Makefile` targets only when:

- Starting/stopping the stack (`make up` / `{{ $assist->sailBinaryPath() }} up -d`, `make down` / `{{ $assist->sailBinaryPath() }} stop`)
- The user explicitly asks for containerized commands
- A task needs services only available inside Compose (e.g. aligning container UIDs via `make fix-permissions`)

## WSL2 + Windows Docker Desktop

Local development is often done in WSL2 while Docker runs via **Windows Docker Desktop** (WSL integration), not a native Linux Docker install inside the distro.

- If `docker` / `docker compose` cannot be found, or Sail fails with "Docker is not running" / cannot connect to the daemon, do **not** assume Docker must be installed inside WSL. Check that Docker Desktop is running on Windows and that WSL integration is enabled for this distro.
- Prefer diagnosing through Docker Desktop (engine status, WSL integration settings) before suggesting `apt install docker` or similar host installs.
- Host PHP/Artisan/tests remain valid even when the Docker engine is down.
