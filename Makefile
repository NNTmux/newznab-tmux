# ──────────────────────────────────────────────────────────────
# NNTmux – Docker / Sail convenience targets
# ──────────────────────────────────────────────────────────────
#
# Run `make` or `make help` to see all available targets.
# See DOCKER.md for full documentation.
#
# Common flags:
#   FORCE=1         Skip confirmation prompts on destructive targets
#   MAINTENANCE=1   Wrap `upgrade` migrations in artisan down/up
#   SERVICE=name    Restrict a target (e.g. logs) to a single service
#   CMD="…"         Free-form command for `artisan`, `exec`, etc.
#
# Requires Docker Compose v2.22+ (uses `pull --ignore-buildable`).
# ──────────────────────────────────────────────────────────────
.DEFAULT_GOAL := help
# Source SEARCH_DRIVER from .env and export as COMPOSE_PROFILES
# so the correct search engine container starts automatically.
-include .env
export COMPOSE_PROFILES ?= $(SEARCH_DRIVER)
# Once `php artisan monitoring:install --sail` has set MONITORING_ENABLED=true and
# created the Grafana key, every compose call (./sail reads COMPOSE_FILE from the
# environment) includes the monitoring overlay, so `make up` / `make down` start
# and stop it with the rest of the stack.
MONITORING_KEY := storage/app/monitoring/public/grafana-jwt.pub
MONITORING_ON  := $(and $(filter true "true" 1,$(MONITORING_ENABLED)),$(wildcard $(MONITORING_KEY)))
ifneq ($(MONITORING_ON),)
export COMPOSE_FILE := docker-compose.yml:docker-compose.monitoring.yml
endif
SAIL           := ./sail
DOCKER_COMPOSE := docker compose
# Optional parameters with safe defaults
SERVICE     ?=
CMD         ?=
FORCE       ?=
MAINTENANCE ?=
# Colors
CYAN   := \033[36m
GREEN  := \033[32m
YELLOW := \033[33m
RED    := \033[31m
BOLD   := \033[1m
RESET  := \033[0m
# ── Confirmation helper ──────────────────────────────────────
# Usage: $(call confirm,Are you sure?)
# Honours FORCE=1 for non-interactive / CI usage.
define confirm
	@if [ "$(FORCE)" = "1" ]; then \
		echo "$(YELLOW)⚠  FORCE=1 set — skipping confirmation.$(RESET)"; \
	else \
		printf "$(YELLOW)⚠  $(1) [y/N] $(RESET)"; \
		read confirm; [ "$$confirm" = "y" ] || [ "$$confirm" = "Y" ] || (echo "Aborted." && exit 1); \
	fi
endef
# ── Pre-flight ───────────────────────────────────────────────
.PHONY: check-env
check-env: ## Verify .env exists before running docker targets
	@if [ ! -f .env ]; then \
		echo "$(RED)✘ .env not found. Run: cp .env.example .env$(RESET)"; \
		exit 1; \
	fi
	@if [ -n "$(filter true "true" 1,$(MONITORING_ENABLED))" ] && [ ! -f $(MONITORING_KEY) ]; then \
		echo "$(YELLOW)⚠  MONITORING_ENABLED=true but $(MONITORING_KEY) is missing; monitoring stays off. Run: php artisan monitoring:install --sail$(RESET)"; \
	fi
# ── Lifecycle ────────────────────────────────────────────────
.PHONY: up
up: check-env ## Start all services in the background
	@$(SAIL) up -d
.PHONY: down
down: ## Stop all services
	@$(SAIL) down
.PHONY: stop
stop: down ## Alias for 'down'
.PHONY: restart
restart: ## Restart all services
	@$(SAIL) restart
.PHONY: recreate
recreate: check-env ## Force-recreate containers without rebuilding
	@$(SAIL) up -d --force-recreate --remove-orphans
.PHONY: build
build: check-env pull ## Pull latest service and base images, then build (cached layers OK)
	@$(SAIL) build --pull
.PHONY: rebuild
rebuild: check-env pull ## Rebuild from scratch with fresh base images and recreate
	@$(SAIL) build --no-cache --pull
	@$(SAIL) up -d --force-recreate --remove-orphans
	@echo "$(GREEN)✔ Rebuild complete.$(RESET)"
.PHONY: pull
pull: check-env ## Always pull latest service images for configured tags (skips buildable)
	@$(DOCKER_COMPOSE) pull --ignore-buildable --policy always
.PHONY: update
update: check-env pull ## Infra-only: pull base images, rebuild with --pull, restart
	@$(SAIL) build --pull
	@$(SAIL) up -d --remove-orphans
	@echo "$(GREEN)✔ Infra updated.$(RESET)"
.PHONY: upgrade
upgrade: update fix-permissions composer-install npm-build ## Full app upgrade: update + perms + composer + npm + migrate + caches
	@if [ "$(MAINTENANCE)" = "1" ]; then $(SAIL) artisan down; fi
	@$(SAIL) artisan migrate --force
	@if [ "$(MAINTENANCE)" = "1" ]; then $(SAIL) artisan up; fi
	@$(MAKE) data-cache
	@$(MAKE) optimize
	@echo "$(GREEN)✔ Upgrade complete.$(RESET)"
.PHONY: fresh
fresh: check-env ## Destroy ALL volumes, rebuild, and start clean (DATA LOSS!)
	$(call confirm,This will destroy all Docker volumes (database, redis, search index). Continue?)
	@$(SAIL) down -v
	@$(DOCKER_COMPOSE) pull --ignore-buildable --policy always
	@$(SAIL) build --no-cache --pull
	@$(SAIL) up -d --force-recreate --remove-orphans
	@echo "$(GREEN)✔ Fresh environment is up. Run 'make artisan cmd=nntmux:install' to initialise.$(RESET)"
# ── Shell Access ─────────────────────────────────────────────
.PHONY: shell
shell: ## Open a bash shell in the app container
	@$(SAIL) shell
.PHONY: root-shell
root-shell: ## Open a root bash shell in the app container
	@$(SAIL) root-shell
.PHONY: tinker
tinker: ## Open a Laravel Tinker session
	@$(SAIL) tinker
# ── Artisan / PHP ────────────────────────────────────────────
.PHONY: artisan
artisan: ## Run an artisan command (usage: make artisan cmd="migrate")
	@$(SAIL) artisan $(cmd)$(CMD)
.PHONY: migrate
migrate: ## Run pending database migrations
	@$(SAIL) artisan migrate --force
.PHONY: migrate-fresh
migrate-fresh: ## Drop all tables and re-run migrations (DATA LOSS!)
	$(call confirm,This will DROP ALL TABLES and re-run every migration. Continue?)
	@$(SAIL) artisan migrate:fresh --force
.PHONY: seed
seed: ## Run database seeders
	@$(SAIL) artisan db:seed --force
.PHONY: cache-clear
cache-clear: ## Clear config / route / view / application caches (and stale bootstrap/cache files)
	@$(SAIL) artisan config:clear
	@$(SAIL) artisan route:clear
	@$(SAIL) artisan view:clear
	@$(SAIL) artisan cache:clear
	@rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/services.php bootstrap/cache/packages.php
	@echo "$(GREEN)✔ Caches cleared.$(RESET)"
.PHONY: optimize
optimize: ## Dev-safe optimize: warm data structures + view cache only (no config/route cache)
	@$(SAIL) artisan view:cache
	@$(SAIL) artisan data:cache-structures
	@echo "$(GREEN)✔ Optimized (dev-safe).$(RESET)"
.PHONY: optimize-deploy
optimize-deploy: ## Production optimize: cache config / routes / views + data structures (bakes absolute paths!)
	@echo "$(YELLOW)⚠  This caches absolute paths from inside the container — only run on the deploy host.$(RESET)"
	@$(SAIL) artisan config:cache
	@$(SAIL) artisan route:cache
	@$(SAIL) artisan view:cache
	@$(SAIL) artisan data:cache-structures
	@echo "$(GREEN)✔ Optimized for deploy.$(RESET)"
.PHONY: queue-work
queue-work: ## Run a foreground queue worker (Ctrl-C to stop)
	@$(SAIL) artisan queue:work
.PHONY: queue-restart
queue-restart: ## Signal all queue workers to restart gracefully
	@$(SAIL) artisan queue:restart
.PHONY: tmux-start
tmux-start: ## Start the NNTmux tmux processing engine
	@$(SAIL) artisan tmux:start
.PHONY: tmux-stop
tmux-stop: ## Stop the NNTmux tmux processing engine
	@$(SAIL) artisan tmux:stop
.PHONY: tmux-attach
tmux-attach: ## Attach to the running tmux session
	@$(SAIL) artisan tmux:attach
.PHONY: horizon
horizon: ## Show Horizon status
	@$(SAIL) artisan horizon:status
# ── Testing & Quality ────────────────────────────────────────
.PHONY: test
test: ## Run the PHPUnit test suite (usage: make test filter=TestName)
	@$(SAIL) test $(if $(filter),--filter=$(filter),)
.PHONY: pint
pint: ## Run Laravel Pint code formatter on dirty files
	@$(SAIL) pint --dirty
.PHONY: pint-all
pint-all: ## Run Laravel Pint on all files
	@$(SAIL) pint
.PHONY: phpstan
phpstan: ## Run PHPStan static analysis
	@$(SAIL) php vendor/bin/phpstan analyse --memory-limit=2G
.PHONY: rector
rector: ## Run Rector in dry-run mode (no changes written)
	@$(SAIL) php vendor/bin/rector process --dry-run
.PHONY: rector-fix
rector-fix: ## Apply Rector refactorings
	@$(SAIL) php vendor/bin/rector process
# ── Frontend ─────────────────────────────────────────────────
.PHONY: npm-build
npm-build: ## Run npm install and build inside the container
	@$(SAIL) npm install
	@$(SAIL) npm run build
.PHONY: npm-dev
npm-dev: ## Start Vite dev server inside the container
	@$(SAIL) npm run dev
.PHONY: ts-types
ts-types: ## Regenerate TypeScript types from PHP DTOs/Enums
	@$(SAIL) artisan typescript:transform
.PHONY: ts-types-check
ts-types-check: ## CI: regenerate TS types and fail if working tree drifts
	@$(SAIL) artisan typescript:transform --quiet
	@git diff --exit-code resources/js/types/generated.d.ts \
		|| (echo "❌ resources/js/types/generated.d.ts is out of date — run 'make ts-types' and commit." && exit 1)
.PHONY: data-cache
data-cache: ## Cache spatie/laravel-data structures (run on deploy)
	@$(SAIL) artisan data:cache-structures
# ── Dependencies ─────────────────────────────────────────────
.PHONY: composer-install
composer-install: ## Run composer install inside the container
	@$(SAIL) composer install
.PHONY: composer-update
composer-update: ## Run composer update inside the container
	@$(SAIL) composer update
# ── Database / Services ──────────────────────────────────────
.PHONY: db
db: ## Open a MariaDB CLI session
	@$(SAIL) mariadb
.PHONY: redis-cli
redis-cli: ## Open a Redis CLI session
	@$(SAIL) redis
# Host UID/GID — used by fix-permissions so files stay writable from BOTH
# the host and the in-container sail user (whose UID should match WWWUSER).
HOST_UID ?= $(shell id -u)
HOST_GID ?= $(shell id -g)
.PHONY: fix-permissions
fix-permissions: ## Align UIDs: remap sail to host UID, chown project, register git safe.directory
	@echo "$(YELLOW)→ Remapping container 'sail' user to UID $(HOST_UID) and chowning /var/www/html…$(RESET)"
	@$(DOCKER_COMPOSE) exec -u root laravel.test usermod -u $(HOST_UID) -o sail >/dev/null 2>&1 || true
	@$(DOCKER_COMPOSE) exec -u root laravel.test groupmod -g $(HOST_GID) -o sail >/dev/null 2>&1 || true
	@$(DOCKER_COMPOSE) exec -u root laravel.test chown -R $(HOST_UID):$(HOST_GID) /var/www/html
	@$(DOCKER_COMPOSE) exec -u root laravel.test git config --system --add safe.directory /var/www/html
	@echo "$(GREEN)✔ Permissions fixed.$(RESET)"
	@if [ "$(WWWUSER)" != "$(HOST_UID)" ] || [ -z "$(WWWUSER)" ]; then 		echo "$(YELLOW)⚠  WWWUSER ($(WWWUSER)) ≠ host UID ($(HOST_UID)). Set WWWUSER=$(HOST_UID) and WWWGROUP=$(HOST_GID) in .env, then run 'make rebuild' to align the container's sail user.$(RESET)"; 	fi
.PHONY: fix-perms
fix-perms: fix-permissions ## Alias for 'fix-permissions'

.PHONY: git-safe-directory
git-safe-directory: ## Register /var/www/html as a git safe.directory inside the container
	@$(DOCKER_COMPOSE) exec -u root laravel.test git config --system --add safe.directory /var/www/html >/dev/null 2>&1 || true
	@$(DOCKER_COMPOSE) exec laravel.test git config --global --add safe.directory /var/www/html >/dev/null 2>&1 || true
# ── Monitoring ───────────────────────────────────────────────
MONITORING_SERVICES := prometheus pushgateway grafana node-exporter mysqld-exporter redis-exporter
# docker-compose.monitoring.yml is an overlay; ./sail only loads docker-compose.yml.
comma               := ,
MONITORING_COMPOSE  := $(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.monitoring.yml $(foreach profile,$(subst $(comma), ,$(COMPOSE_PROFILES)),--profile $(profile))
.PHONY: monitoring-up
monitoring-up: check-env ## Start only the monitoring containers (`make up` includes them once enabled)
	@if [ ! -f storage/app/monitoring/public/grafana-jwt.pub ]; then \
		echo "$(RED)✘ No Grafana JWT key. Run: php artisan monitoring:install --sail$(RESET)"; \
		exit 1; \
	fi
	@$(MONITORING_COMPOSE) up -d
	@echo "$(GREEN)✔ Monitoring started. Open /admin/monitoring (Prometheus: http://127.0.0.1:$(or $(FORWARD_PROMETHEUS_PORT),9090)).$(RESET)"
.PHONY: monitoring-down
monitoring-down: ## Stop and remove only the monitoring containers (data volumes are kept)
	@$(MONITORING_COMPOSE) rm --stop --force $(MONITORING_SERVICES)
.PHONY: monitoring-logs
monitoring-logs: ## Follow logs of the monitoring containers
	@$(MONITORING_COMPOSE) logs -f --tail=100 $(MONITORING_SERVICES)
# ── Logs & Status ────────────────────────────────────────────
.PHONY: logs
logs: ## Tail logs (usage: make logs [SERVICE=mariadb])
	@$(DOCKER_COMPOSE) logs -f --tail=100 $(SERVICE)
.PHONY: tail-laravel
tail-laravel: ## Tail storage/logs/laravel.log inside the app container
	@$(SAIL) exec laravel.test tail -f storage/logs/laravel.log
.PHONY: status
status: ## Show running containers and their status
	@$(DOCKER_COMPOSE) ps
.PHONY: ps
ps: status ## Alias for 'status'
.PHONY: top
top: ## Show running processes inside each container
	@$(DOCKER_COMPOSE) top
.PHONY: images
images: ## Show images used by each service
	@$(DOCKER_COMPOSE) images
.PHONY: health
health: ## Show healthcheck status for each service (Compose v2.20+)
	@$(DOCKER_COMPOSE) ps --format json 2>/dev/null \
		| awk -F'"' '/"Service"/ {svc=$$4} /"Health"/ {print svc": "$$4}' \
		| sort -u \
		|| $(DOCKER_COMPOSE) ps
# ── Cleanup ──────────────────────────────────────────────────
.PHONY: clean
clean: ## Remove stopped containers and dangling images
	@docker system prune -f
	@echo "$(GREEN)✔ Cleaned up dangling resources.$(RESET)"
.PHONY: nuke
nuke: ## Remove ALL project containers, images, and volumes (DATA LOSS!)
	$(call confirm,This will remove ALL project containers/images/volumes. Continue?)
	@$(SAIL) down -v --rmi all
	@echo "$(GREEN)✔ All project Docker resources removed.$(RESET)"
# ── Help ─────────────────────────────────────────────────────
.PHONY: help
help: ## Show this help message, grouped by section
	@echo ""
	@echo "$(CYAN)$(BOLD)NNTmux Docker / Sail Commands$(RESET)"
	@echo "$(CYAN)─────────────────────────────$(RESET)"
	@awk ' \
/^# ── .* ──/ { \
line=$$0; sub(/^# ── /, "", line); sub(/ ──.*/, "", line); \
printf "\n\033[1;33m%s\033[0m\n", line; next \
} \
/^[a-zA-Z_-]+:.*?## / { \
split($$0, a, ":.*?## "); \
split(a[1], b, ":"); \
printf "  \033[32m%-20s\033[0m %s\n", b[1], a[2] \
}' $(MAKEFILE_LIST)
	@echo ""
	@echo "$(CYAN)Flags:$(RESET) FORCE=1  MAINTENANCE=1  SERVICE=name  CMD=\"…\""
	@echo ""
