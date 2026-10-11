# NNTmux docker/ stack lifecycle. Run from anywhere: make -C docker <target>
# Design: docs/2026-10-08-nntmux-docker-stack-design.md
.DEFAULT_GOAL := help
SHELL := /bin/bash

DC       := docker compose --project-directory $(CURDIR) -f $(CURDIR)/docker-compose.yml
APP_RUN  := $(DC) run --rm --no-deps web
ARTISAN  := $(DC) exec -T web php artisan
REPO     := $(abspath $(CURDIR)/..)
HELPER_IMAGE ?= alpine:3.22.2
-include $(CURDIR)/.env
DB_EXEC   = $(DC) exec -T mariadb mariadb -uroot -p"$(DB_ROOTPASSWORD)" $(DB_DATABASE)
s        ?=
cmd      ?=

DATA_DIRS := data/storage data/install data/mariadb data/redis data/manticore data/caddy/data data/caddy/config \
             data/prometheus data/grafana logs/mariadb logs/manticore logs/caddy backups

.PHONY: help
help: ## Show targets
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n",$$1,$$2}'

.PHONY: env
env: ## Generate docker/.env and config/app.env (secrets, NNTP creds); safe to re-run
	@$(CURDIR)/bin/generate-env
	@$(MAKE) --no-print-directory -C $(CURDIR) perms

.PHONY: dirs
dirs: ## Create bind-mount directories
	@cd $(CURDIR) && mkdir -p $(DATA_DIRS)

# The app containers run as 33:33 (www-data). Docker Desktop/OrbStack map bind-mount ownership
# for them; a native Linux host does not, so hand the app mounts to 33 and let group 33 read
# config/app.env (generate-env rewrites it as 0600, so env re-runs this).
.PHONY: perms
perms: dirs ## Linux hosts: let the app containers' user (33:33) use their bind mounts
	@if [ "$$(uname -s)" = Linux ]; then \
		docker run --rm -v $(CURDIR):/stack $(HELPER_IMAGE) sh -c \
			'chown -R 33:33 /stack/data/storage /stack/data/install && chown $(shell id -u):33 /stack/config/app.env && chmod 0640 /stack/config/app.env'; \
	fi

# Deploys (build, up) share one stack, so they go through bin/deploy-guard: one at a time, logged to
# .deploy.log with DEPLOY_BY, and a rebuild is skipped while nothing changed (FORCE=1 overrides).
DEPLOY_BY ?=
GUARD     := DEPLOY_BY="$(DEPLOY_BY)" $(CURDIR)/bin/deploy-guard

.PHONY: build
build: ## Build nntmux/base:local and nntmux/app:local; skipped when nothing changed (FORCE=1, DEPLOY_BY=name)
	@SKIP_IF_UNCHANGED=1 STAMP_ON_SUCCESS=1 REQUIRE_IMAGE=nntmux/app:local FORCE="$(FORCE)" \
		$(GUARD) run build -- $(MAKE) --no-print-directory -C $(CURDIR) build-images

.PHONY: build-images
build-images:
	docker build -t nntmux/base:local -f $(REPO)/Dockerfile $(REPO)
	docker build -t nntmux/app:local -f $(CURDIR)/app/Dockerfile $(REPO)

.PHONY: deploy
deploy: ## Build when changed, then start/recreate the stack, under one lock (DEPLOY_BY=name)
	@FORCE="$(FORCE)" $(GUARD) run deploy -- sh -c '$(MAKE) --no-print-directory -C $(CURDIR) build && $(CURDIR)/bin/deploy-guard verify && $(MAKE) --no-print-directory -C $(CURDIR) up'

.PHONY: init
init: env dirs build ## First-time install: build, migrate+seed, indexes, groups, tuning, predb seed, start
	$(DC) up -d --wait mariadb redis manticore mailpit
	@if [ -f $(CURDIR)/data/install/install.lock ]; then echo "install.lock present; skipping nntmux:deploy-init"; \
	else $(APP_RUN) php artisan nntmux:deploy-init --no-interaction; fi
	$(APP_RUN) php artisan monitoring:install --sail --no-interaction
	$(MAKE) -C $(CURDIR) seed-db
	@# Best effort: public PreDB feeds may be down, and the scheduler polls them every 5 minutes anyway.
	-$(APP_RUN) php artisan predb:import-feed --pages=20
	$(DC) up -d
	@echo "NNTmux: http://localhost:$(or $(APP_PORT),8080)  (admin password: grep ADMIN_PASS docker/config/app.env)"

.PHONY: seed-db
seed-db: ## Apply config/seed/groups.sql + tuning.sql and clear the settings cache
	$(DB_EXEC) < $(CURDIR)/config/seed/groups.sql
	$(DB_EXEC) < $(CURDIR)/config/seed/tuning.sql
	$(APP_RUN) php artisan cache:clear

.PHONY: seed
seed: seed-db ## Re-seed groups/tuning and restart the indexer to pick it up
	$(DC) restart indexer

.PHONY: up
up: ## Start the whole stack (one deploy at a time; DEPLOY_BY=name)
	@$(GUARD) run up -- $(DC) up -d

.PHONY: down
down: ## Stop the stack (tmux session stops cleanly)
	$(DC) down

.PHONY: restart
restart: ## Restart app services (s=service for one)
	$(DC) restart $(or $(s),web horizon scheduler indexer)

.PHONY: ps
ps: ## Service status / health
	$(DC) ps

.PHONY: logs
logs: ## Follow logs (s=service)
	$(DC) logs -f --tail=200 $(s)

.PHONY: tmux
tmux: ## Attach to the indexer tmux session (detach: Ctrl-a d)
	$(DC) exec indexer tmux attach -t nntmux

.PHONY: shell
shell: ## Shell in the web container
	$(DC) exec web bash

.PHONY: artisan
artisan: ## Run artisan: make artisan cmd="predb:import-feed --dry-run"
	$(DC) exec web php artisan $(cmd)

.PHONY: mysql
mysql: ## MariaDB client as root
	$(DC) exec mariadb mariadb -uroot -p"$(DB_ROOTPASSWORD)" $(DB_DATABASE)

.PHONY: backup
backup: ## Dump MariaDB to backups/ (keeps 7)
	@# Dumps hold password hashes and API keys: owner-only. Publish and prune only after the
	@# whole pipeline succeeded, so a failed dump never replaces or rotates out a good backup.
	@set -euo pipefail; umask 077; mkdir -p $(CURDIR)/backups; chmod 0700 $(CURDIR)/backups; \
	out=$(CURDIR)/backups/nntmux-$$(date +%Y%m%d-%H%M%S).sql.gz; tmp=$$out.partial; \
	trap 'rm -f "$$tmp"' EXIT; \
	$(DC) exec -T mariadb mariadb-dump -uroot -p"$(DB_ROOTPASSWORD)" --single-transaction --quick --routines $(DB_DATABASE) \
		| gzip > "$$tmp"; \
	mv "$$tmp" "$$out"; \
	ls -1t $(CURDIR)/backups/nntmux-*.sql.gz | tail -n +8 | xargs -r rm --; \
	ls -lh $(CURDIR)/backups | tail -n 3

.PHONY: status
status: ## Indexing progress snapshot (groups, binaries, releases, predb)
	@echo "SELECT COUNT(*) active_groups, SUM(last_record>0) scanned FROM usenet_groups WHERE active=1; \
	SELECT (SELECT COUNT(*) FROM collections) collections, (SELECT COUNT(*) FROM binaries) binaries, (SELECT COUNT(*) FROM releases) releases; \
	SELECT source, COUNT(*) pres FROM predb GROUP BY source ORDER BY 2 DESC LIMIT 10;" | $(DB_EXEC) -t
