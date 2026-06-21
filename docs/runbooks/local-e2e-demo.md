# Local end-to-end demo runbook

> **Goal.** Run the full TrioSense stack on a developer machine: infra → backend → dashboard → edge → live FIFO updates.
> **Time.** ~20 minutes first run; ~5 minutes once dependencies are installed.

---

## Prerequisites

- Docker Desktop (or Docker Engine + Compose v2)
- PHP 8.3 + Composer (`apps/backend`)
- Node 20 + npm (`apps/dashboard`)
- Poetry (`apps/edge`) — `make edge-install` runs this automatically
- Ports available (see **Port conflicts** below)

---

## 1. Start infrastructure

```bash
cd /path/to/triosense
cp .env.example .env   # if not already present
make up
make health
```

Expected: MySQL, Redis, EMQX, Mailhog report healthy.

### Port conflicts

| Service | Default port | Override |
|---------|--------------|----------|
| Redis | 6379 | `REDIS_PORT=6380` in repo-root `.env` |
| MySQL | 3306 | `DB_PORT=3307` |
| EMQX MQTT | 1883 | — |
| EMQX dashboard | 18083 | admin / public |
| Mailhog UI | 8025 | — |

If local Redis already uses 6379:

```bash
echo 'REDIS_PORT=6380' >> .env
make down && make up
```

Update `apps/backend/.env`:

```
REDIS_PORT=6380
```

---

## 2. Backend setup

```bash
cd apps/backend
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

Local ports (recommended to avoid conflicts):

| Service | Port |
|---------|------|
| Laravel API | **8001** |
| Reverb WebSocket | **8090** |

`apps/backend/.env` excerpt:

```
APP_URL=http://localhost:8001
REDIS_PORT=6380
REVERB_PORT=8090
SANCTUM_STATEFUL_DOMAINS=localhost:3001,127.0.0.1:3001
CORS_ALLOWED_ORIGINS=http://localhost:3001,http://127.0.0.1:3001
```

Start backend processes in **separate terminals**:

```bash
# Terminal A — API
cd apps/backend && php artisan serve --port=8001

# Terminal B — queue worker
make backend-queue

# Terminal C — MQTT subscriber
make backend-mqtt

# Terminal D — FIFO tick dispatcher
make backend-tick

# Terminal E — Reverb
make backend-reverb
```

Verify API:

```bash
curl -s http://localhost:8001/health
```

---

## 3. Dashboard

```bash
cd apps/dashboard
cp .env.example .env.local
npm install
npm run dev
```

Open **http://localhost:3001** (Next.js may bind 3001 if 3000 is taken).

Login: `ops@ttd.gov.in` / `password` (seeded operator). Auth uses **HttpOnly Sanctum session cookies** — no bearer token in browser storage.

`apps/dashboard/.env.local`:

```
NEXT_PUBLIC_API_BASE_URL=http://localhost:8001
NEXT_PUBLIC_REVERB_PORT=8090
```

---

## 4. Edge — synthetic or webcam demo

### Option A: Synthetic MQTT events (fastest)

```bash
make edge-simulate
# or: make edge-simulate with location override in apps/edge README
```

Watch dashboard location tiles update queue counters within ~2 seconds.

### Option B: Mac webcam + YOLO preview

```bash
make edge-webcam
```

- Edge MJPEG preview: http://127.0.0.1:8766
- Dashboard combined view: http://localhost:3001/dashboard/locations/1/preview

---

## 5. Verification checklist

| Step | Command / URL | Expected |
|------|---------------|----------|
| Infra | `make health` | mysql/redis/emqx OK |
| API health | `curl localhost:8001/health` | `{"status":"ok"}` |
| Login | Dashboard `/login` | Redirect to `/dashboard` |
| Live state | `/dashboard/locations/1` | Queue counters + issued vs arrived chart |
| MQTT ingest | `make edge-simulate` | `queue_events` rows increase |
| FIFO tick | Wait 5s after simulate | Status may change to `approaching_cutoff` |
| WebSocket | Location detail live tab | Connection indicator connected |
| Replay | `make replay DATE=2026-06-20 LOCATION=1` | Redis rebuilt from MySQL |

---

## 6. Replay recovery drill

```bash
make replay DATE=2026-06-20 LOCATION=3
curl -s -b /tmp/triosense-cookies.txt -c /tmp/triosense-cookies.txt \
  http://localhost:8001/sanctum/csrf-cookie
# then login + GET /api/v1/locations/3/state
```

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Redis connection refused | Check `REDIS_PORT` matches Docker mapping |
| CORS / 419 on login | Ensure `SANCTUM_STATEFUL_DOMAINS` includes dashboard host:port |
| WebSocket auth fails | Reverb running on 8090; dashboard `NEXT_PUBLIC_REVERB_PORT=8090` |
| No MQTT events | `make backend-mqtt` running; EMQX up on 1883 |
| Blank webcam preview | Port 8766 in use — `make edge-webcam` kills stale process first |

---

## Related docs

- [`apps/backend/README.md`](../../apps/backend/README.md)
- [`apps/dashboard/README.md`](../../apps/dashboard/README.md)
- [`apps/edge/README.md`](../../apps/edge/README.md)
- [`API_CONTRACTS.md`](../../API_CONTRACTS.md)
