# ADR-0001 — MQTT for edge → core transport (not HTTP webhooks)

**Status:** Accepted
**Date:** 2026-06-20
**Deciders:** Triounity Engineering
**Context:** Sprint 2 — Edge ingestion pipeline

---

## Context

The edge service publishes 20–80 events per second per location, plus heartbeats every 5 seconds. The events must reach the backend with low latency (sub-second), survive intermittent network conditions (especially during festival days when 5G fallback may be active), and tolerate the backend being restarted for deploys.

We had three plausible options:

1. **HTTP webhooks.** Each event POSTed to a backend endpoint.
2. **WebSocket from edge to core.** Persistent connection, JSON frames.
3. **MQTT.** Publish/subscribe via a broker (EMQX or Mosquitto).

## Decision

We use **MQTT** with EMQX as the broker.

## Consequences

### Why not webhooks
- TCP handshake + TLS handshake per event is expensive at 20+ events/sec.
- The backend becomes the bottleneck. If the backend is restarting, events are dropped or require complex retry on the edge.
- We would have to build the retry/buffering logic on the edge anyway.

### Why not WebSocket-from-edge
- The edge becomes responsible for connection management to the application server, not a broker. This couples deploy cycles: every backend restart kills the edge's connection.
- No native pub/sub semantics — we would need a routing layer above WebSocket to fan out to multiple subscribers (dashboard, signage, mobile).

### Why MQTT
- Persistent connection from edge to broker, decoupled from backend lifecycle.
- QoS 1 (at-least-once) and last-will-and-testament are built in.
- Broker handles fan-out: dashboard, signage, and mobile can also subscribe to the same topics if we ever want that (and the backend's Reverb still serves the WebSocket layer for browsers).
- Wide ecosystem, mature client libraries, well-understood operational characteristics.
- EMQX specifically: high-throughput, native clustering, MQTT 5.0 support, HTTP/WebSocket bridges.

### Costs
- One more infrastructure component to run, monitor, and secure.
- Per-device TLS client certificates need a PKI (we manage with smallstep/CA in prod).
- Developers need to learn MQTT semantics (topics, QoS, retained messages, LWT).

We accept these costs because the alternatives have larger costs in either reliability (webhooks) or coupling (WebSocket from edge).

## Implementation notes

- **Topic structure:** `triosense/loc/{location_id}/{channel}/{...}`. See [`API_CONTRACTS.md`](../../API_CONTRACTS.md) §3.
- **QoS:** Events use QoS 1. Heartbeats use QoS 0.
- **Auth:** Per-device X.509 client certs. Self-signed in dev; smallstep PKI in prod.
- **Buffering:** Edge buffers in SQLite if broker is unreachable.

## Revisit if

- We outgrow EMQX's clustering at >10× current load (very unlikely for TTD scale).
- A simpler in-house transport with the same guarantees emerges (NATS JetStream is the most plausible competitor).
