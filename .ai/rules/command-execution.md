# Host commands overrule Boost Sail

NNTmux agents must prefer WSL-host commands (`php artisan`, `./vendor/bin/pint`, `npm run build`, etc.) over Laravel Boost's built-in Sail rule that says every command must go through Sail.

- Official Boost override: `.ai/guidelines/sail/core.blade.php` (wins on `boost:install` / `boost:update`).
- Host binaries for generated guidelines: `config/boost.php` → `executable_paths` defaults (`php`, `composer`, `npm`, `vendor/bin/`).
- Use Sail / `Makefile` only for Compose lifecycle or when the user asks for containerized runs.
- On WSL2, Docker often comes from Windows Docker Desktop; missing `docker` usually means Desktop/WSL integration, not a missing apt package.
