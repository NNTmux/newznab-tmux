---
applyTo: "**"
description: Override Boost Sail — prefer WSL host commands for Artisan/tests/tooling
---

# Command execution (overrides Laravel Boost Sail)

This project's instructions **overrule** Boost's built-in Sail guideline that says you MUST run all commands through Sail.

## Default

Run PHP, Artisan, Composer, npm, Pint, PHPStan, and tests on the **WSL host**:

```bash
php artisan test --compact --filter=TestName
./vendor/bin/pint --dirty
./vendor/bin/phpstan analyse --memory-limit=2G
npm run build
php artisan make:test --phpunit ExampleTest --no-interaction
```

Do **not** prefix routine commands with `vendor/bin/sail` or `./sail`.

## Sail / Docker only when needed

Use Sail or `make …` for stack lifecycle (`make up` / `make down`) or when the user explicitly asks for containerized commands.

## WSL2 + Windows Docker Desktop

If `docker` is missing or the daemon is unreachable, check that **Windows Docker Desktop** is running and WSL integration is enabled for this distro. Do not assume Docker must be apt-installed inside WSL. Host Artisan/tests remain valid when Docker is down.

Canonical override source: `.ai/guidelines/sail/core.blade.php` (consumed by `php artisan boost:update`).
