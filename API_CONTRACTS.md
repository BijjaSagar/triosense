# TrioSense — API Contracts

> Three protocols meet here. **REST** for control plane and history. **WebSocket (Reverb)** for live state push. **MQTT** for edge ↔ core.

---

## 1. REST API

**Base URL.** `https://api.triosense.in/api/v1` (production). `http://localhost:8000/api/v1` (local).
**Auth.** Laravel Sanctum bearer token in `Authorization: Bearer <token>`.
**Format.** All responses JSON, all wrapped in `ApiResponse` envelope.

### 1.1 Envelope

Success:
```json
{
  "success": true,
  "data": { ... },
  "meta": { "request_id": "uuid", "timestamp": "2026-06-20T06:42:13Z" }
}
```

Error:
```json
{
  "success": false,
  "error": {
    "code": "validation_failed",
    "message": "Human-readable summary",
    "details": [ { "field": "quota", "issue": "must be greater than 0" } ]
  },
  "meta": { "request_id": "uuid", "timestamp": "2026-06-20T06:42:13Z" }
}
```

HTTP codes: `200` OK, `201` Created, `204` No Content, `400` Bad Request, `401` Unauthorized, `403` Forbidden, `404` Not Found, `409` Conflict, `422` Validation Failed, `429` Rate Limited, `500` Server Error, `503` Service Unavailable.

### 1.2 Authentication

#### `POST /auth/login`
Request:
```json
{ "email": "ops@ttd.gov.in", "password": "•••••••••" }
```
Response 200:
```json
{ "success": true, "data": { "token": "sanctum-token", "user": { ... }, "expires_at": "..." } }
```

#### `POST /auth/logout`
204 No Content.

#### `GET /auth/me`
Response 200:
```json
{ "success": true, "data": { "user_id": 1, "name": "...", "email": "...",
   "roles": ["ttd_admin"], "permissions": [...], "locations": [1,2,3] } }
```

### 1.3 Locations

#### `GET /locations`
Lists locations the caller has access to.

#### `GET /locations/{id}`
Detail view, includes today's `daily_quota`, edge device list, current mode.

#### `PATCH /locations/{id}`
Update `mode`, `festival_mode`, `default_quota`. Requires `location.manage` permission. Audit-logged.

#### `GET /locations/{id}/state`
Returns live state snapshot (read from Redis, falls back to MySQL replay).
```json
{ "success": true, "data": {
  "location_id": 3,
  "as_of": "2026-06-20T06:42:13.123Z",
  "quota": 5000, "issued": 3840, "tokens_remaining": 1160,
  "queue_head": 3841, "queue_tail": 5210,
  "cutoff_position": 5000,
  "status": "cutoff_declared",
  "issuance_rate_per_min": 18.4,
  "arrival_rate_per_min": 22.1,
  "edge_devices": [
    { "device_uid": "edge-bdv-01", "status": "online", "last_heartbeat_at": "..." }
  ]
}}
```

### 1.4 Daily quotas

#### `POST /locations/{id}/quota`
Set or update today's quota.
```json
{ "quota_date": "2026-06-20", "quota": 5500, "notes": "Festival adjustment" }
```
Audit-logged. Cannot reduce `quota` below current `issued`.

#### `GET /locations/{id}/quota?date=2026-06-20`
Retrieve a quota row.

### 1.5 Events (audit log)

#### `GET /locations/{id}/events`
Paginated. Filters: `event_type`, `from`, `to`, `edge_device_id`.

#### `GET /locations/{id}/cutoff-events`
Paginated. Filters: `from`, `to`, `mode`.

#### `GET /locations/{id}/cutoff-accuracy`
Returns predicted vs actual closure history for shadow-mode validation.
```json
{ "success": true, "data": {
  "location_id": 3,
  "from": "2026-05-21",
  "to": "2026-06-20",
  "summary": {
    "days_with_predictions": 7,
    "days_within_tolerance": 6,
    "median_delta": 4,
    "max_delta": 12
  },
  "daily": [
    { "date": "2026-06-19", "predicted_cutoff_position": 4985,
      "actual_closure_position": 4990, "delta_positions": 5,
      "within_tolerance": true, "mode": "shadow" }
  ]
}}
```

#### `GET /locations/{id}/announcements`
Paginated announcement history for a location.

#### `GET /cross-counter/recommendations`
Returns active cross-counter redirection recommendations for the tenant.

### 1.6 Edge & cameras

#### `GET /edge-devices`
Tenant-wide listing with filters.

#### `GET /edge/{device_uid}/config`
**Called by edge devices on boot.** Returns the camera RTSP URLs, tripwire coordinates, and runtime settings for that device.
Auth: per-device API key via `X-Edge-Api-Key` header (separate from user Sanctum tokens). Key hash stored in `edge_devices.api_key_hash`.

Response `data`:
```json
{
  "device_uid": "edge-sim-03",
  "tenant_id": 1,
  "location_id": 3,
  "runtime": {
    "heartbeat_seconds": 5,
    "inference_fps": 15,
    "inference_confidence_threshold": 0.5,
    "inference_backend": "tensorrt",
    "stream_backend": "gstreamer",
    "model_path": "yolov8n.pt",
    "rtsp_reconnect_seconds": 5.0
  },
  "cameras": [
    {
      "camera_id": 17,
      "name": "Bhudevi Entry Tripwire",
      "role": "entry_tripwire",
      "rtsp_url": "rtsp://...",
      "status": "active",
      "tripwire": { "line": [[640,720],[1280,720]], "direction": "down" }
    }
  ]
}
```

Tripwire coordinates are stored in `cameras.tripwire_json` (not `config_json`).

#### `POST /edge/{device_uid}/calibrate`
Edge device uploads a frame; server stores it for the calibration UI.

### 1.7 Operator actions

#### `POST /locations/{id}/cutoff/override`
Force a cutoff state change manually. Requires `location.override` permission. Audit-logged.
```json
{ "action": "force_close" | "force_open" | "set_cutoff",
  "cutoff_position": 5000, "reason": "VIP movement at 7:15" }
```

#### `POST /locations/{id}/announce`
Trigger an immediate manual announcement.
```json
{ "template_code": "cutoff_declared", "languages": ["te","ta","hi","en"] }
```

### 1.8 Notifications

#### `POST /users/me/fcm-token`
Register or update a user's FCM token for push notifications.
```json
{ "fcm_token": "firebase-device-token" }
```
Response `data`: `{ "registered": true }`

### 1.9 Observability

#### `GET /metrics/prometheus`
Unauthenticated operational counters for Prometheus scraping (local/staging). Returns JSON wrapper with `format: "prometheus_text"` and a `metrics` string containing counter/gauge lines (`triosense_fifo_ticks_total`, `triosense_mqtt_events_total`, `triosense_active_locations`).

---

## 2. WebSocket API (Laravel Reverb)

**Endpoint.** `wss://ws.triosense.in/app/{key}` (production). `ws://localhost:8080/app/...` (local).
**Client.** Laravel Echo with the Reverb driver.

### 2.1 Authentication

Bearer token in Echo `auth.headers`. Reverb authorizes against `routes/channels.php`.

### 2.2 Channels

#### `private-location.{location_id}`
**Authorization.** User must be assigned to this location.
**Events:**

`LocationStateUpdated` — emitted whenever live state changes
```json
{
  "location_id": 3,
  "as_of": "...",
  "tokens_remaining": 1160,
  "queue_head": 3841,
  "queue_tail": 5210,
  "cutoff_position": 5000,
  "status": "cutoff_declared",
  "delta": { "cause": "issue_event" | "enter_event" | "exit_event" | "tick" | "override" }
}
```

`CutoffStatusChanged` — emitted only on status transitions
```json
{
  "location_id": 3,
  "previous_status": "approaching_cutoff",
  "new_status": "cutoff_declared",
  "cutoff_position": 5000,
  "decided_at": "..."
}
```

`EdgeDeviceStatusChanged`
```json
{ "location_id": 3, "device_uid": "edge-bdv-01",
  "previous_status": "online", "new_status": "degraded", "reason": "low_confidence" }
```

#### `presence-operations.{tenant_id}`
**Authorization.** Any user with `viewer` role for this tenant.
**Use.** Lets operators see who else is currently watching.

#### `private-signage.{location_id}`
**Authorization.** Token authenticated as a signage device. Read-only.
**Events:** `SignageContentUpdated` with rendered multilingual text payload.

---

## 3. MQTT API (edge ↔ core)

**Broker.** EMQX in production. `mqtts://mqtt.triosense.in:8883`. TLS required.
**Auth.** Per-device X.509 client certificates issued by Triounity PKI.
**QoS.** All event publishes use QoS 1 (at-least-once). Heartbeats use QoS 0.

### 3.1 Topic structure

Pattern: `triosense/loc/{location_id}/{channel}/{...}`

| Topic | Direction | Publisher | Subscriber | Purpose |
| --- | --- | --- | --- | --- |
| `triosense/loc/{id}/event/enter` | edge → core | edge | backend | Person crossed entry tripwire inward |
| `triosense/loc/{id}/event/exit` | edge → core | edge | backend | Person crossed entry tripwire outward |
| `triosense/loc/{id}/event/issue` | edge → core | edge | backend | Token issued at counter window |
| `triosense/loc/{id}/event/reverse` | edge → core | edge | backend | Reversal correction (rare) |
| `triosense/loc/{id}/edge/{device_uid}/heartbeat` | edge → core | edge | backend | Liveness ping |
| `triosense/loc/{id}/edge/{device_uid}/status` | edge → core | edge | backend | Health state change (camera offline, low confidence) |
| `triosense/loc/{id}/state` | core → edge/signage | backend | edge + signage | Authoritative live state broadcast |
| `triosense/loc/{id}/command/{device_uid}` | core → edge | backend | edge | Command directed at a specific device |

### 3.2 Payload schemas

All payloads are JSON, UTF-8. Schema versioning via top-level `v` field (current: `1`).

#### Event payload — `event/enter`, `event/exit`, `event/issue`
```json
{
  "v": 1,
  "device_uid": "edge-bdv-01",
  "camera_id": 17,
  "occurred_at": "2026-06-20T06:42:13.123Z",
  "track_id": "trk-9842",
  "confidence": 0.94,
  "metadata": { "frame_number": 4513421, "bbox": [340, 220, 460, 540] }
}
```

#### Heartbeat — `edge/{device_uid}/heartbeat`
Published every 5 seconds.
```json
{
  "v": 1,
  "device_uid": "edge-bdv-01",
  "timestamp": "2026-06-20T06:42:13Z",
  "uptime_seconds": 84231,
  "cpu_percent": 38.2,
  "mem_percent": 51.7,
  "temp_celsius": 62.5,
  "cameras": [
    { "camera_id": 17, "status": "ok", "fps": 14.8, "last_frame_at": "..." },
    { "camera_id": 18, "status": "degraded", "fps": 4.2, "reason": "low_light" }
  ],
  "buffer_size": 0
}
```

#### State broadcast — `loc/{id}/state`
Published whenever backend emits a state change. Same shape as the dashboard `LocationStateUpdated` event.

#### Command — `loc/{id}/command/{device_uid}`
```json
{
  "v": 1,
  "command_id": "uuid",
  "issued_at": "...",
  "action": "close_entry" | "open_entry" | "play_announcement" | "calibrate" | "restart",
  "params": {
    "cutoff_position": 5000,
    "announcement_template_code": "cutoff_declared",
    "languages": ["te","ta","hi","en"]
  }
}
```
Edge must publish an ACK on `triosense/loc/{id}/edge/{device_uid}/ack` within 5 seconds.

### 3.3 Rules

- **Idempotency.** Every event payload includes `device_uid + occurred_at + track_id` as a natural dedup key. Backend rejects duplicates silently (returns OK to broker).
- **Clock sync.** Edge devices run `chrony` to sync with NTP. Drift >5s triggers a DEGRADED status. Backend uses `received_at` as the canonical ordering key, not `occurred_at`.
- **Buffer replay.** If edge has buffered events (after a network outage), it publishes them in `occurred_at` order with the original timestamp. Backend accepts replays for up to 24 hours.

---

## 4. Versioning

- **REST.** URL-versioned (`/api/v1/...`). Breaking changes go to `/api/v2/...`.
- **WebSocket.** Channel names are versioned via the channel pattern. Event payloads carry no version; we change them via deprecation period.
- **MQTT.** Payloads carry `v` field. Backend supports the last 2 schema versions concurrently.

---

## 5. Rate limiting

| Endpoint group | Limit |
| --- | --- |
| `/auth/login` | 5 per minute per IP |
| `/locations/{id}/state` | 60 per minute per user |
| `/locations/{id}/cutoff/override` | 6 per minute per user |
| All other authed REST | 120 per minute per user |
| Edge config polling | 6 per minute per device |
| MQTT publish (per edge) | 500 messages/sec hard cap (broker config) |

---

## 6. OpenAPI

The canonical OpenAPI spec lives at `docs/api/openapi.yaml` and is regenerated from the Laravel route definitions by a CI step. Serve UI via Swagger at `/api/docs` (admin-only in prod).
