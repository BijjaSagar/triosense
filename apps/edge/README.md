# TrioSense Edge

Computer vision service that runs on NVIDIA Jetson Orin Nano at each TTD counter location.

## Quick start (dev — no real cameras)

```bash
cd apps/edge
poetry install
poetry run triosense-edge-simulate --location-id=1
# or equivalently:
poetry run python -m triosense_edge.simulate --location-id=1
```

This publishes synthetic ENTER/EXIT/ISSUE events to the MQTT broker as if a real edge device were detecting people. Use it to drive the backend FIFO loop without hardware.

## Vision pipeline (Sprint 5)

Mock mode for CI/local dev (no RTSP, no GPU):

```bash
cd apps/edge
poetry install
poetry run triosense-edge --config=config/location_3.example.yaml
```

The example YAML sets `inference_backend: mock` and `stream_backend: mock`. A white rectangle moves across synthetic frames; tripwire crossings publish `enter`/`exit` MQTT events.

### Production deployment

```bash
# 1. Fetch camera config from backend on boot
export TRIOSENSE_EDGE_DEVICE_UID=edge-bdv-01
export TRIOSENSE_EDGE_API_KEY=<provisioned-key>
export TRIOSENSE_API_BASE_URL=https://api.triosense.in
export TRIOSENSE_MQTT_HOST=mqtt.triosense.in
export TRIOSENSE_MQTT_PORT=8883
export TRIOSENSE_MQTT_TLS=true
poetry run triosense-edge --fetch-config

# 2. Or use a local YAML at /etc/triosense/edge.yaml
poetry run triosense-edge --config=/etc/triosense/edge.yaml
```

A `systemd` unit file in `infra/systemd/triosense-edge.service` ensures auto-restart on failure.

### Tripwire calibration UI

```bash
poetry run triosense-edge-calibrate --config=config/location_3.example.yaml --port=8765
# Open http://127.0.0.1:8765 — click two points, choose direction, Save
```

### Makefile shortcuts

From repo root:

```bash
make edge-pipeline    # mock pipeline against location_3 example config
make edge-webcam      # Mac webcam demo (YOLO + tripwire on device 0)
make edge-calibrate   # calibration web UI on :8765
make edge-test
```

## Mac webcam demo (local)

Grant **camera permission** to Terminal or Cursor when macOS prompts on first run.

```bash
# 1. Start stack (EMQX on :1883, backend, dashboard)
make up
make seed

# 2. In another terminal — real YOLO on Mac webcam
make edge-webcam
```

Walk across the tripwire line in frame; watch MQTT `enter`/`exit` events on the dashboard live view for location 1.

**Preview URLs**

- Edge MJPEG preview (while `make edge-webcam` is running): [http://127.0.0.1:8766](http://127.0.0.1:8766)
- Dashboard preview page: [http://localhost:3001/dashboard/locations/1/preview](http://localhost:3001/dashboard/locations/1/preview)

Configure the tripwire via dashboard: [http://localhost:3001/dashboard/locations/1/settings](http://localhost:3001/dashboard/locations/1/settings) (login: `ops@ttd.gov.in` / `password`).

## Module map

| Module | Purpose |
| --- | --- |
| `triosense_edge.config` | Pydantic models loaded from YAML |
| `triosense_edge.config_loader` | Backend API config fetch + TensorRT export |
| `triosense_edge.pipeline.runner` | Orchestrator — spawns one task per camera |
| `triosense_edge.pipeline.stream` | RTSP intake via GStreamer (OpenCV/mock fallbacks) |
| `triosense_edge.pipeline.detector` | YOLOv8 inference (TensorRT on Jetson) |
| `triosense_edge.pipeline.tracker` | ByteTrack-style IoU tracker |
| `triosense_edge.pipeline.tripwire` | Line-crossing logic — emits ENTER/EXIT |
| `triosense_edge.transport.mqtt_client` | Async paho-mqtt wrapper with FIFO replay |
| `triosense_edge.transport.buffer` | SQLite buffer for offline replay |
| `triosense_edge.calibration.server` | Local web UI for drawing tripwire lines |
| `triosense_edge.simulate` | Synthetic event publisher for dev |

## Jetson + TensorRT setup

1. Flash JetPack 6 on Orin Nano; install Poetry and project deps.
2. Download base weights once: `yolo export model=yolov8n.pt` or use `triosense-edge --export-model --config=...`.
3. Set in backend `edge_devices.config_json`:
   - `inference_backend: tensorrt`
   - `stream_backend: gstreamer`
   - `model_path: yolov8n.engine`
4. Ensure GStreamer RTSP plugins: `sudo apt install gstreamer1.0-plugins-{bad,good,ugly}`.
5. Validate on hardware:
   - **15 FPS:** watch heartbeat `cameras[].fps` ≥ 14 sustained for 5 minutes.
   - **97% accuracy:** manual count of 100 corridor crossings vs MQTT `enter` events (document in ops runbook).

`inference_backend: cpu` and `stream_backend: opencv` are supported for office dev machines without TensorRT.

## Hardware notes

- **Inference target:** YOLOv8n at 1080p, 15 FPS, with TensorRT.
- **First-run model export:** `triosense-edge --export-model` converts the `.pt` model to a TensorRT `.engine` optimised for the local Jetson. Required after every Ultralytics version bump.
- **RTSP recovery:** stream loop reconnects within `rtsp_reconnect_seconds` (default 5s) on disconnect.
- **Offline buffer:** SQLite FIFO at `buffer_db_path`; drained in insertion order on MQTT reconnect.
- **Power:** Always behind a 1 KVA UPS. Boot-to-operational target: <90 seconds.

## Read before editing

- [`.cursor/rules/04-edge-python.mdc`](../../.cursor/rules/04-edge-python.mdc)
- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) §3 (MQTT)
