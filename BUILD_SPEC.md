# TrioSense — Build Specification

> **Audience.** Triounity engineers + AI coding agents (Cursor, Claude Code, Antigravity).
> **Sprint cadence.** 1 week per sprint. 10 sprints to live cutover at one counter + 2 sprints to three-counter expansion.
> **Definition of done.** Every sprint has acceptance criteria. A sprint is not complete until all ACs pass on the CI pipeline and a human has reviewed.

---

## Sprint 0 — Repository setup & local dev environment

**Goal.** Anyone can clone the repo, run `docker compose up`, and have a working dev environment in under 15 minutes.

**Tasks.**
1. Initialize Git repo with this scaffold. Push to GitHub under `triounity-tech/triosense`.
2. Set up `docker-compose.yml` for MySQL 8, Redis 7, EMQX, Mailhog.
3. Create `.env.example` files for all four apps (backend, dashboard, mobile, edge).
4. Configure GitHub Actions CI:
   - `backend.yml` — composer install, PHPStan level 8, Pest tests
   - `dashboard.yml` — npm install, eslint, type-check, Vitest
   - `edge.yml` — ruff, mypy strict, pytest
   - `mobile.yml` — flutter analyze, flutter test
5. Add `Makefile` with common commands: `make up`, `make down`, `make test`, `make lint`.
6. Add `.editorconfig`, `.gitignore`, `.gitattributes`, `LICENSE` (proprietary).
7. Document every step in `README.md`.

**Acceptance criteria.**
- [ ] `git clone && docker compose up -d && make health` returns OK for all services
- [ ] CI green on initial commit
- [ ] New engineer onboarding doc verified by one fresh person

---

## Sprint 1 — Core domain schema + auth

**Goal.** All durable tables exist, seeded with the three TTD locations. Operators can log in.

**Backend tasks.**
1. Install Laravel 11 in `apps/backend`. Set up Sanctum, Spatie Permission.
2. Write migrations for: `tenants`, `locations`, `counters`, `daily_quotas`, `edge_devices`, `cameras`, `users`, `roles`, `permissions`, `audit_logs`.
   - Every operational table has `tenant_id BIGINT UNSIGNED NOT NULL` and `location_id BIGINT UNSIGNED NOT NULL`.
   - Every table has indexed `created_at` and `updated_at`.
3. Write Eloquent models with strict `$fillable`, `$casts`, and relations.
4. Write `DatabaseSeeder` that seeds TTD (tenant 1) and the three locations with their daily quotas.
5. Write `App\Http\Resources\ApiResponse` trait for JSON envelopes.
6. Write `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`.
7. Write `App\Policies\LocationPolicy` enforcing tenant + role scoping.

**Dashboard tasks.**
1. Bootstrap Next.js 15 App Router in `apps/dashboard`.
2. Tailwind 4 + shadcn/ui setup.
3. Login page that calls `POST /api/v1/auth/login` and persists Sanctum token in HttpOnly cookie.
4. Empty `/dashboard` shell with sidebar showing the three locations.

**Mobile tasks.**
1. Flutter 3 init in `apps/mobile` with feature-first folder structure: `lib/features/{auth,locations,settings}/`.
2. BLoC + GoRouter setup. Hive for offline cache.
3. Login screen.
4. Empty locations list screen.

**Acceptance criteria.**
- [ ] `php artisan migrate:fresh --seed` creates the schema and seeds TTD + 3 locations
- [ ] PHPStan level 8 clean on backend
- [ ] Operator can log into dashboard, sees 3 location tiles
- [ ] Operator can log into mobile app, sees 3 location tiles
- [ ] All endpoints documented in `docs/api/openapi.yaml`

---

## Sprint 2 — Edge ingestion pipeline

**Goal.** A simulated edge device can publish ENTER events; backend persists them and updates Redis.

**Backend tasks.**
1. Add EMQX to `docker-compose.yml` and test broker reachable.
2. Install `php-mqtt/laravel-client`.
3. Create `App\Console\Commands\MqttSubscriberCommand` that runs as a long-lived daemon.
4. Subscriber routes incoming messages to handlers per topic pattern:
   - `triosense/loc/+/event/enter` → `EnterEventHandler`
   - `triosense/loc/+/event/exit` → `ExitEventHandler`
   - `triosense/loc/+/event/issue` → `IssueEventHandler`
   - `triosense/loc/+/edge/+/heartbeat` → `HeartbeatHandler`
5. Each handler:
   - Validates payload against a JSON schema
   - Inserts into `queue_events` (or updates `edge_devices.last_heartbeat_at`)
   - Atomically updates Redis state via a Lua script
6. Write Pest tests with a fake MQTT client.

**Edge tasks.**
1. Set up `apps/edge` Python project with Poetry.
2. Implement `triosense_edge.simulate` module that publishes synthetic ENTER/EXIT/ISSUE events at configurable rates.
3. Document how to connect a real camera (RTSP URL, tripwire line coordinates) — actual YOLOv8 implementation comes in Sprint 5.

**Infra.**
1. EMQX configured with per-device TLS client certs (dev: self-signed; prod: from PKI).

**Acceptance criteria.**
- [ ] `python -m triosense_edge.simulate --location-id=1` publishes 1 event/sec
- [ ] Backend subscriber inserts rows into `queue_events`
- [ ] Redis key `triosense:loc:1:queue_tail` increments
- [ ] Backend handles malformed payloads without crashing (logged + ignored)
- [ ] Heartbeats older than 30s mark edge device as STALE

---

## Sprint 3 — FIFO decision engine

**Goal.** The cutoff calculator works correctly under all input conditions, with 95%+ test coverage.

**Backend tasks.**
1. Create `App\Domain\Fifo\CutoffCalculator` — pure class, no I/O.
   - Method: `decide(LiveState $state): Decision`
   - Decision: `{status, cutoff, reason}`
2. Write `App\Jobs\FifoTickJob` scheduled every 1 second per location via Laravel scheduler.
3. Job pulls live state from Redis, calls calculator, persists `cutoff_events` if status changed, broadcasts via Reverb.
4. Write `App\Jobs\RehydrateLiveStateJob` for cold-Redis recovery.
5. Write extensive Pest tests covering:
   - OPEN state when arrivals are well below quota
   - APPROACHING_CUTOFF when arrival rate forecasts overflow
   - CUTOFF_DECLARED when current queue already exceeds remaining
   - Edge cases: zero issuance rate, zero arrival rate, quota = 0, head > tail
   - Replay scenarios from real-looking event sequences

**Acceptance criteria.**
- [ ] Pest coverage on `App\Domain\Fifo` ≥ 95%
- [ ] FIFO tick runs at 1Hz per location without lag
- [ ] Replay test: 12 hours of synthetic events arrives at expected cutoff position within ±2 positions of analytical answer

---

## Sprint 4 — Dashboard live view (read-only)

**Goal.** TTD operators can see the live state of all three counters in real time.

**Dashboard tasks.**
1. Connect Laravel Echo to Reverb. Subscribe to `private-location.{id}` per location.
2. Build the live counter card:
   - Big number: tokens remaining
   - Queue progress bar: head → tail → cutoff position
   - Status badge: OPEN / APPROACHING / DECLARED / CLOSED
   - Edge device health pills
   - Last update timestamp (stale > 5s = red)
3. Build the three-location grid view (`/dashboard`).
4. Build per-location detail view (`/dashboard/locations/[id]`):
   - Real-time chart of issued vs arrived (Recharts)
   - Recent queue events table (last 50)
   - Edge device status panel
5. Add loading skeletons, error boundaries, and a stale-data banner.

**Backend tasks.**
1. Implement Reverb broadcaster: `LocationStateUpdatedEvent`.
2. Implement `GET /api/v1/locations/{id}/state` for initial dashboard load.
3. Implement `GET /api/v1/locations/{id}/events` (paginated).

**Acceptance criteria.**
- [ ] Open dashboard in 2 browser tabs → both update within 1 second of any state change
- [ ] Killing the WebSocket connection shows a "Reconnecting…" banner; reconnect restores state
- [ ] Mobile-responsive (operators may use phones)

---

## Sprint 5 — Real edge vision (YOLOv8 + ByteTrack)

**Goal.** A Jetson Orin Nano with a real IP camera produces accurate IN/OUT events.

**Edge tasks.**
1. Install Ultralytics YOLOv8 on Jetson. Verify TensorRT acceleration works.
2. Implement `triosense_edge.pipeline`:
   - GStreamer pipeline: RTSP → decode → frame
   - YOLOv8n inference (people class only)
   - ByteTrack multi-object tracking
   - Tripwire-crossing detector: IN if track crosses a configured line in direction D, OUT if reverse
3. Configuration file format: `config/location_3.yaml` with camera URLs, tripwire line coordinates per camera, role assignment.
4. Local SQLite buffer with FIFO replay on reconnect.
5. Calibration script: stream frames to a local web UI for tripwire line drawing.

**Backend tasks.**
1. Calibration storage: `cameras.config_json` field with tripwire coordinates.
2. Edge config provisioning endpoint: `GET /api/v1/edge/{device_id}/config` returns the camera configs for that device.

**Test environment.**
1. Use one real Jetson + one Hikvision camera in the office, pointing at a corridor.
2. Run a manual count of 100 people crossing and compare to edge-reported count.
3. Acceptance: ≥97% accuracy for steady-state walking traffic, ≥90% for clustered/burst traffic.

**Acceptance criteria.**
- [ ] Test run: 100 manual crossings vs edge-reported count ≥97% match
- [ ] Inference at 15 FPS on 1080p sustained
- [ ] Pipeline self-recovers from RTSP disconnect in <10 seconds
- [ ] SQLite buffer replays correctly after network outage

---

## Sprint 6 — Shadow mode + LED signage

**Goal.** A real counter runs the full system in shadow mode for a day; predicted cutoff matches actual closure within ±10 positions.

**Backend tasks.**
1. Implement `Mode::SHADOW` vs `Mode::LIVE` per location (env-driven).
2. In SHADOW mode:
   - All decisions calculated normally
   - `cutoff_events` written with `mode='shadow'`
   - No MQTT commands to edge
   - No PA announcements
   - Dashboard shows "SHADOW MODE" banner
3. Build `GET /api/v1/locations/{id}/cutoff-accuracy` returning predicted vs actual closure history.

**Dashboard tasks.**
1. Build "Shadow Mode Performance" tab with daily accuracy chart.

**Signage tasks.**
1. Build `apps/signage/index.html`: pure HTML + JS, WebSocket subscriber.
2. Renders large text: position counter, status, multilingual rotation.
3. Test on a real LED panel through HDMI.

**Acceptance criteria.**
- [ ] One counter runs in shadow mode for 7 consecutive days
- [ ] Predicted cutoff vs actual closure: median delta ≤ 10 positions
- [ ] Signage renders correctly on the target outdoor LED panel
- [ ] No false closures, no missed events on heartbeat monitoring

---

## Sprint 7 — Live cutoff + PA announcements

**Goal.** Live cutover at the pilot counter. System closes entry tripwire and plays announcements autonomously.

**Backend tasks.**
1. Implement MQTT command publisher.
2. On status `CUTOFF_DECLARED`, publish:
   - `triosense/loc/{id}/command/{device_id}` with `{"action":"close_entry","cutoff_position":5000}`
3. Build PA announcement engine:
   - Pre-generate TTS audio files per announcement type per language using ElevenLabs or Coqui TTS
   - On status change, publish HTTP POST to PA controller (or MQTT topic) with audio file path
4. Build `App\Models\AnnouncementTemplate` with placeholders (`{cutoff_position}`, `{tokens_remaining}`).
5. Languages: Telugu, Tamil, Hindi, English. Templates approved by TTD ops in advance.

**Edge tasks.**
1. Subscribe to command topic.
2. Implement entry tripwire close: a soft signal that the operator sees (red overhead light + dashboard alert) — physical barrier control is out of scope for v1.

**Dashboard tasks.**
1. Manual override panel (with audit logging): force open, force close, adjust quota.
2. Announcement history viewer.

**Acceptance criteria.**
- [ ] Live cutover at pilot counter for one full operating day
- [ ] No incorrect early closures
- [ ] No missed closures (system always closes within 5 minutes of actual quota exhaustion)
- [ ] All operator overrides logged with full audit trail

---

## Sprint 8 — Mobile supervisor app

**Goal.** Supervisors and the EO can monitor and intervene from their phones.

**Mobile tasks.**
1. Live counter screen (mirroring dashboard counter card).
2. Push notifications (Firebase Cloud Messaging) triggered by:
   - APPROACHING_CUTOFF status
   - Edge device offline > 2 minutes
   - Manual override applied
3. Override controls (gated by `location_supervisor` role).
4. Offline-first: last-known state cached in Hive, shown with a "stale" badge if older than 60s.

**Backend tasks.**
1. FCM token registration endpoint.
2. Push notification sender service.

**Acceptance criteria.**
- [ ] Notifications arrive within 3 seconds of trigger event
- [ ] App works fully offline for read view (shows cached last state)
- [ ] Override applied from mobile is visible on dashboard within 1 second

---

## Sprint 9 — Expansion to three counters + cross-counter logic

**Goal.** All three TTD counter locations live simultaneously, with optional cross-location redirection.

**Tasks.**
1. Deploy edge kits at Vishnu Nivasam and Srinivasam (hardware + config).
2. Multi-location dashboard view tested under realistic load.
3. Cross-counter redirection logic:
   - If location A is `CUTOFF_DECLARED` AND location B has `tokens_remaining > queue_length + buffer`, surface a recommendation on dashboard and mobile.
   - Optionally announce: "Tokens still available at [Other Location]" via PA.
4. Festival mode toggle:
   - Adjusts thresholds (announcement triggers fire earlier)
   - Increases polling cadence
   - Disables cross-counter redirection (all three are full anyway)

**Acceptance criteria.**
- [ ] Three counters running independently with no shared-resource contention
- [ ] Cross-counter recommendation appears on dashboard when conditions met
- [ ] Festival mode tested with simulated 10× load

---

## Sprint 10 — Hardening, runbooks, handover

**Goal.** Production-ready system with operations team trained.

**Tasks.**
1. Load testing: simulate 20× normal event rate; system must not lose events or lag the FIFO loop by more than 2 seconds.
2. Failure mode testing: kill each component (edge, broker, backend, Redis, MySQL) and verify documented recovery behavior.
3. Write runbooks for every documented failure mode in `docs/runbooks/`.
4. Write `docs/runbooks/incident-response.md` with on-call procedure.
5. Train TTD ops on dashboard, mobile app, override procedures.
6. Train TTD electronics team on edge hardware physical maintenance.
7. Set up monitoring: Prometheus metrics, Grafana dashboards, Sentry for errors.

**Acceptance criteria.**
- [ ] Load test passes
- [ ] All failure modes recoverable per runbook within target time
- [ ] TTD ops team can independently handle a simulated incident
- [ ] Monitoring dashboards exist and alert TTD ops on critical states

---

## Cross-sprint commitments

These rules apply to every sprint:

1. **No sprint completes without tests.** Pest for PHP, Vitest for TS, pytest for Python, Flutter test for Dart.
2. **No sprint completes without docs.** Every new endpoint goes into `docs/api/`. Every architectural choice goes into `docs/adr/`.
3. **Migrations are append-only.** Never modify a deployed migration. Add a new one.
4. **No `console.log` / `dd()` / `print()` in committed code.** Use proper logging.
5. **All env vars documented in `.env.example`.** Adding one without documenting it is a CI failure.
6. **PHPStan level 8.** TypeScript strict mode. Mypy strict.
7. **Conventional commits.** `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`.

---

## Deliverables at end of pilot programme

- [ ] One TTD counter running TrioSense live for 14+ consecutive days
- [ ] Three counters provisioned with hardware
- [ ] All operational runbooks written and rehearsed
- [ ] TTD ops team trained and signed off
- [ ] Handover document signed by Triounity and TTD EO
- [ ] Source code in TTD's own GitHub organization (mirrored from Triounity's)
- [ ] AMC contract in place for Year 1
