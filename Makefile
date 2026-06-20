SHELL := /bin/bash

.PHONY: help up down logs ps health restart \
        backend-shell backend-test backend-stan backend-migrate backend-seed \
        dashboard-dev dashboard-build dashboard-test \
        edge-test edge-lint \
        mobile-test mobile-analyze \
        seed clean

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ---------- infra ----------

up: ## Start infrastructure (mysql, redis, emqx, mailhog)
	docker compose up -d mysql redis emqx mailhog
	@echo "✓ Infra up — waiting for healthchecks..."
	@docker compose ps

down: ## Stop infrastructure
	docker compose down

logs: ## Tail infrastructure logs
	docker compose logs -f

ps: ## Show container status
	docker compose ps

health: ## Check service health
	@echo "MySQL:"
	@docker compose exec -T mysql mysqladmin -uroot -p$${DB_ROOT_PASSWORD:-rootsecret} ping || true
	@echo "Redis:"
	@docker compose exec -T redis redis-cli ping || true
	@echo "EMQX dashboard: http://localhost:18083 (admin/public)"

restart: down up

# ---------- backend ----------

backend-shell: ## PHP shell in backend container
	cd apps/backend && php artisan tinker

backend-test: ## Run Pest test suite
	cd apps/backend && ./vendor/bin/pest

backend-stan: ## Run PHPStan
	cd apps/backend && ./vendor/bin/phpstan analyse --memory-limit=2G

backend-migrate: ## Run pending migrations
	cd apps/backend && php artisan migrate

backend-seed: ## Reset and reseed database
	cd apps/backend && php artisan migrate:fresh --seed

backend-mqtt: ## Start the MQTT subscriber daemon
	cd apps/backend && php artisan triosense:mqtt-subscribe

backend-tick: ## Start the FIFO tick worker
	cd apps/backend && php artisan triosense:fifo-tick

backend-reverb: ## Start the Reverb WebSocket server
	cd apps/backend && php artisan reverb:start

backend-queue: ## Start the queue worker
	cd apps/backend && php artisan queue:work redis

# ---------- dashboard ----------

dashboard-dev: ## Start Next.js dev server
	cd apps/dashboard && npm run dev

dashboard-build: ## Production build
	cd apps/dashboard && npm run build

dashboard-test: ## Run Vitest
	cd apps/dashboard && npm test

# ---------- edge ----------

edge-test: ## Run pytest
	cd apps/edge && poetry run pytest

edge-lint: ## Ruff + mypy
	cd apps/edge && poetry run ruff check . && poetry run mypy triosense_edge

edge-simulate: ## Run a synthetic event publisher (location 1 by default)
	cd apps/edge && poetry run python -m triosense_edge.simulate --location-id=1

# ---------- mobile ----------

mobile-test: ## Run Flutter tests
	cd apps/mobile && flutter test

mobile-analyze: ## Static analysis
	cd apps/mobile && flutter analyze

# ---------- utility ----------

seed: backend-seed ## Alias

clean: ## Remove all containers and volumes — DESTRUCTIVE
	docker compose down -v
