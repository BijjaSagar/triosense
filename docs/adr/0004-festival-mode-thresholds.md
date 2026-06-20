# ADR-0004 — Festival mode safety margin

**Status:** Accepted  
**Date:** 2026-06-20  
**Context:** Sprint 9 — festival mode toggle

## Decision

When `locations.festival_mode = true`:

1. `CutoffCalculator` uses a **20% safety margin** (vs 10% default) for arrival forecast buffering.
2. FIFO tick interval reduces to 500ms via `TRIOSENSE_FIFO_FESTIVAL_TICK_INTERVAL_MS`.
3. Cross-counter redirection is **disabled** (all counters expected full).

## Consequences

Earlier APPROACHING_CUTOFF declarations on high-traffic days. Ops must toggle festival mode via dashboard PATCH before peak hours.
