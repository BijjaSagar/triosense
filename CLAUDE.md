# CLAUDE.md — Agent Instructions for TrioSense

> **Read this in full before writing any code in this repo.** This file is read by Claude Code, Cursor's agent, and any other AI assistant. It encodes the rules and conventions that human reviewers will enforce.

---

## Project at a glance

- **What it is.** A crowd-aware token distribution system for TTD's three SSD counters.
- **Why it exists.** To prevent devotees from waiting hours in line only to learn that tokens have run out.
- **The clever bit.** A FIFO state machine that compares live queue length against tokens remaining, in real time, and declares the exact cutoff position.
- **Who owns it.** Triounity Technologies. Client: Tirumala Tirupati Devasthanams.

Read [`ARCHITECTURE.md`](./ARCHITECTURE.md) before doing anything else. It tells you *why* the system is shaped this way.

---

## How to work in this repo

### Before writing code

1. **Find the relevant sprint** in [`BUILD_SPEC.md`](./BUILD_SPEC.md). If your task is not in any sprint, ask the human before proceeding.
2. **Find the relevant module README** (`apps/backend/README.md`, etc.).
3. **Find the relevant Cursor rule** in `.cursor/rules/`.

### When writing code

1. **Honor existing conventions.** If a similar file exists, mirror its structure exactly. Do not introduce a different pattern unless asked.
2. **Add tests in the same PR as the code.** No exceptions.
3. **Add docs in the same PR as the code.** New endpoint → `API_CONTRACTS.md` + `docs/api/openapi.yaml`. New table → `DATABASE_SCHEMA.md`. New architectural choice → `docs/adr/NNN-title.md`.
4. **Never silently swallow errors.** Use proper logging or re-raise.

### When in doubt

Ask. Do not guess on:
- Multi-tenancy scoping (which table needs `tenant_id`, how to scope a query)
- FIFO state machine logic (read `App\Domain\Fifo\CutoffCalculator` carefully before changing)
- MQTT topic naming (must follow the convention in [`API_CONTRACTS.md`](./API_CONTRACTS.md))
- Anything that touches Redis live state (one wrong DECR can corrupt the counter)

---

## Hard rules

### Database
- Every operational table has `tenant_id BIGINT UNSIGNED NOT NULL`.
- Every operational table has `location_id BIGINT UNSIGNED NOT NULL`.
- Money: always `DECIMAL(15,2)`. Never float.
- Timestamps: Laravel's `timestamps()` for `created_at`/`updated_at`. Domain events use `occurred_at TIMESTAMP(3)`.
- Foreign keys: every FK gets an explicit index.
- Soft deletes only on top-level entities (`tenants`, `locations`, `users`). Operational tables (`queue_events`, `cutoff_events`) are append-only.
- Migrations are append-only. **Never** modify a deployed migration. Add a new one.

### Backend (Laravel)
- PHP 8.3. Strict types where supported.
- PHPStan level 8. No baselines.
- Pest for tests. No PHPUnit-style.
- Every controller is thin. Domain logic lives in `App\Domain\*`.
- Every JSON response uses the `App\Http\Resources\ApiResponse` envelope.
- Every queue job is idempotent. Use job-id deduplication.
- Payment-like jobs (don't exist yet but for the rule): `tries=1`, no retries.
- Never call external HTTP from a request handler — always queue.
- No `dd()`, `var_dump()`, `print_r()` in committed code.

### Frontend (Next.js + React)
- TypeScript strict mode. No `any`.
- React Server Components by default; mark client components with `'use client'` only when necessary.
- Tailwind 4 with shadcn/ui. No CSS modules unless absolutely required.
- Data fetching: server actions + Laravel Sanctum cookie auth.
- WebSocket: Laravel Echo, single shared instance via React context.
- No `console.log` in committed code.

### Mobile (Flutter)
- Feature-first folder layout: `lib/features/{feature}/{data,domain,presentation}/`.
- BLoC for all state management. No Provider, no Riverpod, no GetX.
- Hive for local cache. SQLite only if we hit a Hive limitation.
- GoRouter for navigation.
- Every screen has a loading state, error state, and offline state.
- No `print()`. Use `dart:developer` `log()` or `package:logger`.

### Edge (Python)
- Python 3.11. Type hints everywhere.
- Ruff (linter) + mypy --strict.
- Pytest for tests. No unittest-style.
- All I/O via `asyncio`. No blocking calls in the main loop.
- Configuration via Pydantic models loaded from YAML.
- Inference must run on Jetson with TensorRT acceleration. If something works only on CPU, that's a bug.

### MQTT
- Topic patterns are locked in [`API_CONTRACTS.md`](./API_CONTRACTS.md). Do not invent new patterns without an ADR.
- Every payload has top-level `v` (schema version).
- QoS 1 for events; QoS 0 for heartbeats.
- TLS only. Self-signed certs only in `.env.example` local dev; real PKI in staging and prod.

---

## Multi-tenancy: how to query correctly

Every query against an operational table MUST be scoped by `tenant_id`. The current tenant comes from the authenticated user.

```php
// ❌ WRONG — leaks across tenants
$events = QueueEvent::where('location_id', $locationId)->get();

// ✅ CORRECT — scoped to current tenant
$events = QueueEvent::query()
    ->where('tenant_id', auth()->user()->tenant_id)
    ->where('location_id', $locationId)
    ->get();
```

Better: use the `App\Models\Concerns\BelongsToTenant` trait, which adds a global scope automatically. Models that use the trait MUST be queried with `tenant_id` in the calling context — global scopes can be forgotten under `withoutGlobalScopes()`.

---

## The FIFO loop: do not break it

`App\Domain\Fifo\CutoffCalculator` is the single source of cutoff logic. It is a pure class — no I/O, no Eloquent calls, no Redis calls. It receives a `LiveState` value object and returns a `Decision` value object.

If you need to change cutoff logic:
1. Open an ADR (`docs/adr/`).
2. Write the new logic in `CutoffCalculator`.
3. Add Pest tests covering the new behavior.
4. Run the replay test on a real day's data to verify no regression.

If you need to change side effects of a status change:
1. Edit `App\Jobs\FifoTickJob`.
2. Add an integration test.

Do not split FIFO logic across multiple classes. Keep it in one place where it can be reasoned about.

---

## Redis: atomic operations only

Every Redis update that touches multiple keys must use a Lua script or `MULTI`/`EXEC`. Two separate calls will create a race condition.

```php
// ❌ WRONG — race condition between the two operations
Redis::decr("triosense:loc:{$id}:tokens_remaining");
Redis::incr("triosense:loc:{$id}:queue_head");

// ✅ CORRECT — atomic
Redis::transaction(function ($tx) use ($id) {
    $tx->decr("triosense:loc:{$id}:tokens_remaining");
    $tx->incr("triosense:loc:{$id}:queue_head");
});
```

Even better: use a Lua script stored in `apps/backend/scripts/lua/` and loaded via `Redis::eval()`.

---

## Audit logging

Every operator action that mutates state writes to `audit_logs`. Use the `App\Services\AuditLogger` service — never raw inserts.

```php
$auditLogger->record(
    action: 'cutoff.overridden',
    entity: $location,
    before: $previousState,
    after: $newState,
    reason: $request->input('reason'),
);
```

---

## File creation rules

- New backend controller → `apps/backend/app/Http/Controllers/Api/V1/`
- New domain class → `apps/backend/app/Domain/{Aggregate}/`
- New job → `apps/backend/app/Jobs/`
- New migration → `apps/backend/database/migrations/` (timestamped, never edited after merge)
- New seeder → `apps/backend/database/seeders/`
- New dashboard page → `apps/dashboard/app/{route}/page.tsx`
- New dashboard component → `apps/dashboard/components/{feature}/`
- New mobile feature → `apps/mobile/lib/features/{feature}/`
- New edge module → `apps/edge/triosense_edge/{module}/`

---

## Things that will get a PR rejected

- New table without `tenant_id` and `location_id` (unless top-level: `tenants`, `users`).
- Money column as `FLOAT` or `DOUBLE`.
- Multi-key Redis update without a transaction or Lua.
- `console.log` / `dd()` / `print()` in committed code.
- New endpoint not documented in `API_CONTRACTS.md`.
- New env var not in `.env.example`.
- Modifying a deployed migration.
- Bypassing `ApiResponse` envelope in backend responses.
- Bypassing `AuditLogger` for state mutations.
- Mixing FIFO logic into a controller or job (must stay in `App\Domain\Fifo`).
- Any change to `triosense/loc/*` MQTT topic structure without an ADR.

---

## Things that will get a PR fast-tracked

- Tests for an existing untested code path.
- An ADR for a non-obvious decision.
- A runbook for a failure mode that didn't have one.
- A performance improvement with before/after benchmark.
- Reducing dependencies.

---

## Useful commands

```bash
make up                # docker compose up -d
make down              # docker compose down
make logs              # tail logs across services
make backend-shell     # PHP container shell
make backend-test      # Pest test suite
make backend-stan      # PHPStan
make dashboard-dev     # Next.js dev server
make dashboard-test    # Vitest
make edge-test         # pytest
make mobile-test       # flutter test
make seed              # reset DB and reseed
make replay DATE=2026-06-19 LOCATION=3   # replay a day's events into Redis
```
