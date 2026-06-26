# TrioSense

> A crowd-aware token distribution system for the Tirumala Tirupati Devasthanams (TTD) Slotted Sarva Darshan (SSD) counters.

**Built by Triounity Technologies.**

TrioSense uses computer vision at three SSD counter locations (Vishnu Nivasam, Srinivasam, Bhudevi Complex) to count devotees joining and leaving the queue in real time, compares this count against the tokens still available, and broadcasts the exact cutoff position to LED signage, PA announcements, and a TTD operations dashboard.

The system does not change TTD's established issuance process — it augments it with a feedback signal.

---

## Repository layout

```
triosense/
├── apps/
│   ├── backend/          Laravel 11 — central FIFO engine, API, Reverb WebSocket
│   ├── dashboard/        Next.js 15 — TTD operations control room
│   ├── mobile/           Flutter 3 — Supervisor app for EO and shift staff
│   ├── edge/             Python 3.11 — YOLOv8 + ByteTrack edge inference
│   └── signage/          Static HTML — LED counter signage (runs on display device)
├── infra/
│   ├── docker/           Dockerfiles for all services
│   ├── mqtt/             EMQX / Mosquitto configuration
│   ├── nginx/            Reverse proxy + TLS configuration
│   └── scripts/          Deploy, backup, and ops scripts
├── docs/
│   ├── adr/              Architecture Decision Records
│   ├── api/              Generated OpenAPI specs
│   └── runbooks/         On-call runbooks
├── .cursor/rules/        Cursor AI rules (auto-loaded by IDE)
├── ARCHITECTURE.md       System architecture — read first
├── BUILD_SPEC.md         Sprint-by-sprint development plan
├── DATABASE_SCHEMA.md    Full MySQL DDL with rationale
├── API_CONTRACTS.md      REST + WebSocket + MQTT contracts
├── CLAUDE.md             AI agent instructions (read by Cursor/Claude Code)
├── AGENTS.md             High-level agent behavior rules
└── docker-compose.yml    Local development environment
```

---

## Quick start (local development)

### Prerequisites
- Docker Desktop or Docker Engine 24+
- Node.js 20+ (for the dashboard)
- PHP 8.3 + Composer (for the backend)
- Flutter 3.24+ (for the mobile app)
- Python 3.11+ (for edge dev)

### Spin up infrastructure
```bash
git clone https://github.com/BijjaSagar/triosense.git triosense
cd triosense
cp .env.example .env
docker compose up -d mysql redis emqx mailhog
```

This brings up:
- MySQL 8 on `localhost:3306`
- Redis 7 on `localhost:6379`
- EMQX MQTT broker on `localhost:1883` (MQTT) and `localhost:18083` (dashboard)
- Mailhog on `localhost:8025` (for transactional email testing)

### Recommended local startup order (Makefile)

EMQX can report **health: starting** for 10–20s after `make up`. Starting `make backend-mqtt` before the broker accepts TCP on `1883` fails with `ConnectingToBrokerFailedException`.

```bash
make up
make wait-emqx          # or: sleep 15 && make health   # EMQX line should show "pong"
make backend-serve-docker   # API http://localhost:8001
# Redis workers (use Docker on macOS if host PHP has no phpredis):
make backend-queue-docker
make backend-tick-docker
make backend-reverb-docker  # WebSocket http://localhost:8080
# separate terminals (host PHP OK for MQTT / edge; do not Ctrl+Z — use fg or restart):
make backend-mqtt
make edge-simulate
make dashboard-dev        # http://localhost:3001 — only one instance (EADDRINUSE if already running)
```

**macOS host PHP without phpredis:** `make backend-queue` and `make backend-tick` need the `redis` PECL extension. If `pecl install redis` fails (e.g. missing `igbinary.h`), answer **no** to all optional serializer prompts, or run:

```bash
pecl install -D 'enable-redis-igbinary=no' redis
```

Otherwise use the `*-docker` worker targets above (`triosense-php:8.4-local` image includes phpredis and `infra/docker/backend-serve.env` for the compose network).



### Start the backend
```bash
cd apps/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan reverb:start &
php artisan queue:work redis &
php artisan serve
```

Backend now running at `http://localhost:8000`.

### Start the dashboard
```bash
cd apps/dashboard
npm install
cp .env.local.example .env.local
npm run dev
```

Dashboard now running at `http://localhost:3000`.

### Start an edge simulator
```bash
cd apps/edge
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
python -m triosense_edge.simulate --location-id=1 --camera-role=ENTRY_TRIPWIRE
```

This will publish synthetic events to the MQTT broker as if a real camera were detecting people crossing the entry tripwire.

### Run the mobile app
```bash
cd apps/mobile
flutter pub get
flutter run
```

---

## Where to read next

| If you are… | Start here |
| --- | --- |
| **Reading the codebase for the first time** | [`ARCHITECTURE.md`](./ARCHITECTURE.md) |
| **Picking up a sprint** | [`BUILD_SPEC.md`](./BUILD_SPEC.md) |
| **Adding a new endpoint or event** | [`API_CONTRACTS.md`](./API_CONTRACTS.md) |
| **Touching the database** | [`DATABASE_SCHEMA.md`](./DATABASE_SCHEMA.md) |
| **Using Cursor or Claude Code on this repo** | [`CLAUDE.md`](./CLAUDE.md) and `.cursor/rules/` |
| **Deploying to production** | [`docs/runbooks/deployment.md`](./docs/runbooks/deployment.md) |

---

## Naming conventions (locked)

- **Code & filenames:** `snake_case` for PHP, `camelCase` for TypeScript/Dart, `snake_case` for Python.
- **Database tables:** plural, `snake_case` (`queue_events`, not `QueueEvent`).
- **Database columns:** `snake_case`, never abbreviated (`tokens_remaining`, not `tokens_rem`).
- **Multi-tenancy column:** `tenant_id BIGINT UNSIGNED NOT NULL` on every tenant-scoped table.
- **Location scoping:** `location_id BIGINT UNSIGNED NOT NULL` on every operational table.
- **Money:** `DECIMAL(15,2)` always — never floats.
- **Timestamps:** `created_at`, `updated_at` (Laravel defaults). Domain events use `occurred_at`.
- **API endpoints:** plural-resource REST (`/api/v1/locations/{id}/queue-events`).
- **WebSocket channels:** dot-namespaced (`location.{id}`, `presence-operations`).
- **MQTT topics:** slash-namespaced (`triosense/loc/{id}/event/enter`).

---

## Brand & ownership

- **Owner:** Triounity Technologies
- **Client:** Tirumala Tirupati Devasthanams (TTD)
- **License:** Proprietary. All rights reserved.

---

## Contact

- Tech lead: Triounity Technologies engineering
- Issues: GitHub Issues with appropriate sprint label
- Production incidents: see [`docs/runbooks/incident-response.md`](./docs/runbooks/incident-response.md)
