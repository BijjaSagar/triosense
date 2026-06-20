SHELL := /bin/bash

# Prefer Docker Compose V2 plugin; fall back to standalone docker-compose.
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")

.PHONY: help up down logs ps health restart \
        backend-shell backend-test backend-stan backend-migrate backend-seed \
        dashboard-dev dashboard-build dashboard-test dashboard-lint \
        edge-install edge-test edge-lint edge-pipeline edge-calibrate edge-webcam edge-simulate \
        mobile-test mobile-analyze \
        test lint \
        seed clean

help: ## Show this help
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ---------- infra ----------

up: ## Start infrastructure (mysql, redis, emqx, mailhog)
	$(COMPOSE) up -d mysql redis emqx mailhog
	@echo "✓ Infra up — waiting for healthchecks..."
	@$(COMPOSE) ps

down: ## Stop infrastructure
	$(COMPOSE) down

logs: ## Tail infrastructure logs
	$(COMPOSE) logs -f

ps: ## Show container status
	$(COMPOSE) ps

health: ## Check service health
	@echo "=== Container status ==="
	@$(COMPOSE) ps
	@echo "MySQL:"
	@if $(COMPOSE) ps --status running -q mysql 2>/dev/null | grep -q .; then \
		$(COMPOSE) exec -T mysql mysqladmin -uroot -p$${DB_ROOT_PASSWORD:-rootsecret} ping; \
	else echo "  skip — mysql container not running (run: make up)"; fi
	@echo "Redis:"
	@if $(COMPOSE) ps --status running -q redis 2>/dev/null | grep -q .; then \
		$(COMPOSE) exec -T redis redis-cli ping; \
	else echo "  skip — redis container not running (if port 6379 is taken, set REDIS_PORT=6380 in .env)"; fi
	@echo "EMQX:"
	@if $(COMPOSE) ps --status running -q emqx 2>/dev/null | grep -q .; then \
		$(COMPOSE) exec -T emqx emqx ping; \
	else echo "  skip — emqx container not running (run: make up)"; fi
	@echo "Mailhog UI: http://localhost:8025"
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

dashboard-lint: ## ESLint + type-check
	cd apps/dashboard && npm run lint && npm run type-check

# ---------- edge ----------

edge-install: ## Install edge Python dependencies (Poetry)
	cd apps/edge && poetry install

edge-test: ## Run pytest
	cd apps/edge && poetry run pytest

edge-lint: ## Ruff + mypy
	cd apps/edge && poetry run ruff check . && poetry run mypy triosense_edge

edge-simulate: edge-install ## Run a synthetic event publisher (location 1 by default)
	cd apps/edge && poetry run python -m triosense_edge.simulate --location-id=1

edge-pipeline: edge-install ## Run vision pipeline in mock mode (location 3 example config)
	cd apps/edge && poetry run triosense-edge --config=config/location_3.example.yaml

edge-calibrate: ## Tripwire calibration web UI on :8765 (mock/RTSP example config)
	cd apps/edge && poetry run triosense-edge-calibrate --config=config/location_3.example.yaml --port=8765

edge-calibrate-webcam: edge-install ## Tripwire calibration on Mac webcam (:8765)
	cd apps/edge && poetry run triosense-edge-calibrate --config=config/local.webcam.yaml --port=8765

edge-webcam: edge-install ## Mac webcam demo — YOLO + tripwire + preview on :8766
	cd apps/edge && mkdir -p /tmp/triosense && poetry run triosense-edge --config=config/local.webcam.yaml --preview-port=8766

# ---------- mobile ----------

mobile-test: ## Run Flutter tests
	cd apps/mobile && flutter test

mobile-analyze: ## Static analysis
	cd apps/mobile && flutter analyze

# ---------- all apps ----------

test: backend-test dashboard-test edge-test mobile-test ## Run all test suites

lint: backend-stan dashboard-lint edge-lint mobile-analyze ## Run all linters

# ---------- utility ----------

seed: backend-seed ## Alias

clean: ## Remove all containers and volumes — DESTRUCTIVE
	$(COMPOSE) down -v
