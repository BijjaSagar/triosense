# ADR-0005 — MQTT commands and PA announcements on LIVE mode only

**Status:** Accepted  
**Date:** 2026-06-20  
**Context:** Sprint 7 — live cutoff + PA

## Decision

1. `FifoTickSideEffectHandler` runs on status transitions from `FifoTickService`.
2. When `locations.mode = shadow`: no MQTT `close_entry`, no PA, no FCM (predictions only).
3. When `locations.mode = live`: publish `triosense/loc/{id}/command/{device_uid}` with `close_entry` on CUTOFF_DECLARED; trigger PA via `AnnouncementService`; notify supervisors via FCM.
4. PA uses pre-approved `announcement_templates` with placeholder rendering; HTTP POST to PA controller when `TRIOSENSE_PA_CONTROLLER_URL` is set, otherwise stub marks announcements as played.

## Consequences

Shadow-mode rollout can validate cutoff accuracy without affecting devotees. Live cutover requires explicit PATCH to `mode=live` per location.
