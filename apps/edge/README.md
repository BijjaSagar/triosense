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

This publishes synthetic ENTER/EXIT/ISSUE events to the MQTT broker at ~1 event/sec, as if a real camera were detecting people. Use it to drive the backend FIFO loop without hardware.

## Production deployment

```bash
poetry run triosense-edge --config=/etc/triosense/edge.yaml
```

A `systemd` unit file in `infra/systemd/triosense-edge.service` ensures auto-restart on failure.

## Module map

| Module | Purpose |
| --- | --- |
| `triosense_edge.config` | Pydantic models loaded from YAML |
| `triosense_edge.pipeline.runner` | Orchestrator — spawns one task per camera |
| `triosense_edge.pipeline.stream` | RTSP intake via GStreamer |
| `triosense_edge.pipeline.detector` | YOLOv8 inference (TensorRT on Jetson) |
| `triosense_edge.pipeline.tracker` | ByteTrack multi-object tracking |
| `triosense_edge.pipeline.tripwire` | Line-crossing logic — emits ENTER/EXIT |
| `triosense_edge.transport.mqtt_client` | Async paho-mqtt wrapper |
| `triosense_edge.transport.buffer` | SQLite buffer for offline replay |
| `triosense_edge.calibration.server` | Local web UI for drawing tripwire lines |
| `triosense_edge.simulate` | Synthetic event publisher for dev |

## Hardware notes

- **Inference target:** YOLOv8n at 1080p, 15 FPS, with TensorRT.
- **First-run model export:** `triosense-edge export-model` converts the .pt model to a TensorRT .engine optimised for the local Jetson. Required after every Ultralytics version bump.
- **Power:** Always behind a 1 KVA UPS. Boot-to-operational target: <90 seconds.

## Read before editing

- [`.cursor/rules/04-edge-python.mdc`](../../.cursor/rules/04-edge-python.mdc)
- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) §3 (MQTT)
