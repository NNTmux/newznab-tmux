# ──────────────────────────────────────────────────────────────
# NNTmux – Docker / Sail convenience targets
# ──────────────────────────────────────────────────────────────
#
# Run `make` or `make help` to see all available targets.
# See DOCKER.md for full documentation.
# ──────────────────────────────────────────────────────────────

.DEFAULT_GOAL := help

# Source SEARCH_DRIVER from .env and export as COMPOSE_PROFILES
# so the correct search engine container starts automatically.
-include .env
export COMPOSE_PROFILES ?= $(SEARCH_DRIVER)

SAIL := ./sail
DOCKER_COMPOSE := docker compose

# Colors
CYAN   := \033[36m
GREEN  := \033[32m
YELLOW := \033[33m
RESET  := \033[0m

# ── Lifecycle ────────────────────────────────────────────────

.PHONY: up
up: ## Start all services in the background
	@$(SAIL) up -d

.PHONY: down
down: ## Stop all services
	@$(SAIL) down

.PHONY: stop
stop: down ## Alias for 'down'

.PHONY: restart
restart: ## Restart all services
	@$(SAIL) restart

.PHONY: build
build: ## Build the app image
	@$(SAIL) build

.PHONY: rebuild
rebuild: ## Build the app image from scratch (no cache)
	@$(SAIL) build --no-cache

.PHONY: pull
pull: ## Pull the latest versions of all base images
	@$(DOCKER_COMPOSE) pull

.PHONY: update
update: pull rebuild ## Pull latest images, rebuild, and restart
	@$(SAIL) up -d

.PHONY: fresh
fresh: ## Destroy ALL volumes, rebuild, and start clean (DATA LOSS!)
	@echo "$(YELLOW)⚠  This will destroy all Docker volumes (database, redis, search index).$(RESET)"
	@read -p "Are you sure? [y/N] " confirm && [ "$$confirm" = "y" ] || exit 1
	@$(SAIL) down -v
	@$(SAIL) build --no-cache
	@$(SAIL) up -d
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
	@$(SAIL) artisan $(cmd)

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

# ── Logs & Status ────────────────────────────────────────────

.PHONY: logs
logs: ## Tail logs from all containers
	@$(DOCKER_COMPOSE) logs -f --tail=100

.PHONY: status
status: ## Show running containers and their status
	@$(DOCKER_COMPOSE) ps

# ── Cleanup ──────────────────────────────────────────────────

.PHONY: clean
clean: ## Remove stopped containers and dangling images
	@docker system prune -f
	@echo "$(GREEN)✔ Cleaned up dangling resources.$(RESET)"

.PHONY: nuke
nuke: ## Remove ALL project containers, images, and volumes (DATA LOSS!)
	@echo "$(YELLOW)⚠  This will remove ALL project containers, images, and volumes.$(RESET)"
	@read -p "Are you sure? [y/N] " confirm && [ "$$confirm" = "y" ] || exit 1
	@$(SAIL) down -v --rmi all
	@echo "$(GREEN)✔ All project Docker resources removed.$(RESET)"

# ── Help ─────────────────────────────────────────────────────

.PHONY: help
help: ## Show this help message
	@echo ""
	@echo "$(CYAN)NNTmux Docker / Sail Commands$(RESET)"
	@echo "$(CYAN)─────────────────────────────$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""

