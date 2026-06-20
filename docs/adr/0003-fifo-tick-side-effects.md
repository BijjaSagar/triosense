# ADR-0003 — FIFO tick side effects via FifoTickService and Reverb

**Status:** Accepted
**Date:** 2026-06-20
**Deciders:** Triounity Engineering
**Context:** Sprint 3 — FIFO decision engine

---

## Context

The FIFO loop runs at 1 Hz per location. Each tick must:

1. Read live counters from Redis (see [ADR-0002](./0002-redis-as-live-state.md)).
2. Run the pure `CutoffCalculator` to produce a `Decision`.
3. Write the updated status and cutoff back to Redis.
4. Notify dashboards, mobile, and signage in sub-second time.
5. Persist an audit row when the counter *status* changes (for shadow-mode accuracy analysis).

We had to decide **where side effects live**, **what gets written to MySQL on every tick vs only on transitions**, and **which transport carries live state to browser clients**.

Plausible options for each concern:

| Concern | Options considered |
| --- | --- |
| Side-effect orchestration | Logic inside `FifoTickJob` · Dedicated `FifoTickService` · Event-sourced saga |
| Durable decision log | Every tick → `cutoff_events` · Status transitions only · No table (Redis only) |
| UI push transport | Reverb (WebSocket) · MQTT `loc/{id}/state` · Polling REST |

## Decision

We adopt a **three-layer split**:

1. **`CutoffCalculator`** (pure domain) — no I/O. Input: `LiveState`. Output: `Decision`. Never modified for side effects.
2. **`LiveStateReader`** + **`LocationRedisStateWriter`** — Redis I/O only. Reader builds `LiveState` and a pre-tick `LocationLiveSnapshot`. Writer applies decision outputs atomically via `MULTI`/`EXEC`.
3. **`FifoTickService`** — orchestrates one tick: read → decide → write Redis → persist → broadcast. **`FifoTickJob`** is a thin, idempotent queue wrapper (`ShouldBeUnique` per location, 1-second window).

**Persistence rule:** Insert into `cutoff_events` **only when `status` changes** (e.g. `open → approaching_cutoff`). Do not insert a row on every 1 Hz tick or when only `cutoff_position` moves within the same status.

**Broadcast rule:** Dispatch `LocationStateUpdated` via Laravel Reverb when any material field changes (status, cutoff position, tokens remaining, queue head/tail). Dispatch `CutoffStatusChanged` **additionally** when status transitions.

**Deferred side effects (later sprints):** MQTT commands to edge (tripwire close), PA announcements, FCM push, and `daily_quotas.closed_at` updates are **not** triggered from `FifoTickService` in Sprint 3. They will be wired behind the same status-transition hooks in Sprint 6–8, gated by `Mode::SHADOW` vs `Mode::LIVE`.

**Disabled locations:** If `locations.mode = disabled`, the tick returns immediately — no Redis write, no DB insert, no broadcast.

## Consequences

### Why not side effects inside `FifoTickJob`

- Jobs are infrastructure (retries, uniqueness, queue naming). Mixing orchestration with queue mechanics makes unit testing require a running worker.
- A dedicated service is testable with `Event::fake()`, seeded Redis, and in-memory SQLite without dispatching a job.

### Why not event-sourced saga

- Overkill for a single-location, 1 Hz loop with four statuses. Adds indirection without reducing operational risk at TTD scale.
- Replay remains anchored on `queue_events` + `cutoff_events`, not an internal saga log.

### Why `cutoff_events` on status change only, not every tick

- At 3 locations × 86,400 ticks/day, full tick logging produces ~260 K rows/day of mostly identical `open` rows — expensive and useless for shadow-mode analysis.
- Shadow-mode acceptance criteria compare **predicted status transitions** against actual closure, not every intermediate calculator invocation.
- Cutoff position updates within `cutoff_declared` are reflected in Redis and broadcast via Reverb; they do not need a new audit row unless status also changed.

### Why Reverb for UI push, not MQTT `loc/{id}/state`

- Dashboard, mobile (via Pusher protocol), and signage already authenticate to Reverb with Sanctum / device tokens. One WebSocket path for all browser-native clients.
- MQTT state topic remains available for edge/signage fallback (see [`API_CONTRACTS.md`](../../API_CONTRACTS.md) §3), but the **primary** Sprint 3–4 path is Reverb → Laravel Echo.
- Pushing state over MQTT *and* Reverb on every tick would duplicate fan-out and complicate idempotency on constrained edge devices.

### Why broadcast on cutoff move, not only status change

- ARCHITECTURE.md §5.4 requires re-announcing when cutoff position moves forward within `cutoff_declared`. Signage must update even if status stays `cutoff_declared`.
- `CutoffStatusChanged` remains status-transition-only; `LocationStateUpdated` carries the full live snapshot.

### Costs

- Two broadcast event types to maintain (`LocationStateUpdated`, `CutoffStatusChanged`).
- Developers must remember: **calculator = pure**, **service = side effects**, **job = dispatch only**.
- Redis must be reachable for ticks; MySQL outage does not block ticks but loses audit rows until recovery (acceptable — Redis state still drives live display).

We accept these costs to keep the FIFO loop testable, auditable at the right granularity, and aligned with the existing WebSocket contract.

## Implementation notes

- **Redis keys:** `triosense:loc:{id}:*` via `App\Domain\Fifo\LocationRedisKeys`. Multi-key updates in `LocationRedisStateWriter::apply()` use the Lua script at `scripts/lua/apply_fifo_decision.lua`.
- **Rate fields:** `issuance_rate_per_min` and `arrival_rate_per_min` read from Redis; default `0.0` if absent (calculator skips forecast below minimum issuance rate).
- **Mode mapping:** `cutoff_events.mode` is `shadow` or `live` based on `locations.mode`. Rows are not written for `disabled`.
- **Channels:** `private-location.{id}` per [`API_CONTRACTS.md`](../../API_CONTRACTS.md) §2.2.
- **Scheduler:** `FifoTickJob` dispatched once per second per active location (Artisan command / scheduler — Sprint 3 follow-up).
- **Tests:** Pest feature tests in `tests/Feature/Jobs/FifoTickJobTest.php` cover status transition, idempotent consecutive ticks, disabled mode, and job dispatch.

## Revisit if

- We need per-tick forensic replay (would require logging every tick to `cutoff_events` or a separate `fifo_tick_log` table).
- Signage moves to MQTT-only with no WebSocket (would promote MQTT state broadcast to primary).
- Cross-location coordination (Sprint 9) requires atomic multi-location Redis updates — may need Lua scripts spanning keys.
- Tick frequency exceeds 1 Hz (festival mode) and Reverb fan-out becomes a bottleneck — consider coalescing broadcasts.
