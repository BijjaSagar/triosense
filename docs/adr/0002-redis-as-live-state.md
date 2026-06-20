# ADR-0002 — Redis as the live decision surface, MySQL as durable audit

**Status:** Accepted
**Date:** 2026-06-20
**Deciders:** Triounity Engineering
**Context:** Sprint 3 — FIFO decision engine

---

## Context

The FIFO decision loop runs at 1Hz per location. Each tick reads the current state (tokens remaining, queue head, queue tail), runs the calculator, and broadcasts the result. Under bursty load, individual ENTER events may arrive at >50 Hz.

We need two things from our state store:
1. **Read/write atomicity for counter increments** (one bad race condition can mis-state the cutoff and turn devotees away wrongly).
2. **Durability of the underlying event log** (regulatory, recovery, auditability).

These two requirements pull in opposite directions. Optimising for one penalises the other.

## Decision

- **Redis** is the live decision surface. Every state read for the FIFO loop comes from Redis. Every counter increment is atomic via Lua scripts or `MULTI/EXEC`.
- **MySQL** is the durable source of truth. Every event arriving from MQTT is written to `queue_events` *before* the Redis update fires.
- Redis is treated as a derived cache. Losing Redis must never lose decision-making data — only require a brief rebuild.
- A `RehydrateLiveStateJob` replays today's `queue_events` to rebuild Redis state on cold start.

## Consequences

### Why not MySQL alone
- MySQL's row-locking under 50+ Hz of `UPDATE counters SET ... WHERE id = ?` would create lock contention and unpredictable tick latency.
- The FIFO calculator must see consistent state. MySQL transactions can provide this, but at a cost in latency that pushes us over the 1-second tick budget.

### Why not Redis alone
- Redis persistence (RDB + AOF) is best-effort. Power-loss between fsync windows can lose minutes of data.
- We need queue events for analytics, replay, and audit. These are non-negotiable.

### Why both
- MySQL absorbs every event durably. If Redis dies, we replay.
- Redis serves the hot path with atomic counter operations at high throughput.
- The MQTT subscriber writes to MySQL *first*, then updates Redis. If the process crashes between, the next tick's rehydration reconciles.

## Implementation rules

1. **Always durable first.** Subscriber order is: insert into `queue_events` → update Redis. Never reverse.
2. **Multi-key Redis updates use Lua or `MULTI`.** A single ISSUE event must atomically `DECR tokens_remaining` and `INCR queue_head`. Two separate calls is a bug.
3. **Redis keys have TTL.** All live state keys for a location expire at end of operating day (configurable per location). Rehydration runs at day-open.
4. **Never read state from MySQL in the FIFO tick.** That's a bug. The tick reads Redis only.

## Revisit if

- We add multi-region deployment (Redis replication lag becomes a factor).
- Event volume grows ≥10× (probably move to a stream like Redis Streams or Kafka).
- We need cross-location FIFO state coordination at sub-second latency (different problem, may need a different tool).
