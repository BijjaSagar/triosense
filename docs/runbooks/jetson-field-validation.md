# Jetson + RTSP field validation

> **Purpose.** Validate edge vision pipeline on NVIDIA Jetson hardware with production RTSP cameras before go-live.
> **Hardware.** Jetson Orin Nano / Xavier NX with JetPack 6.x, static IP on counter VLAN.

---

## Preconditions

- [ ] Jetson flashed with JetPack; CUDA + TensorRT available
- [ ] RTSP URL reachable from Jetson (`ffprobe -rtsp_transport tcp rtsp://...`)
- [ ] TLS MQTT client cert installed (`infra/mqtt/certs/`)
- [ ] Location config YAML deployed (`config/location_<id>.yaml`)
- [ ] Tripwire calibrated (`triosense-edge-calibrate`)

---

## 1. RTSP connectivity test

```bash
# On Jetson
ffprobe -v error -show_entries stream=width,height,r_frame_rate \
  -rtsp_transport tcp -i "rtsp://camera-host/stream1"
```

Pass: video stream reported, no timeout in 10s.

---

## 2. RTSP reconnect test

Simulate network blip while pipeline runs:

```bash
# Terminal A — run pipeline
poetry run triosense-edge --config=config/location_3.yaml

# Terminal B — block RTSP port for 30s (adjust IP/port)
sudo iptables -A OUTPUT -d <camera-ip> -p tcp --dport 554 -j DROP
sleep 30
sudo iptables -D OUTPUT -d <camera-ip> -p tcp --dport 554 -j DROP
```

Pass criteria:

- [ ] Pipeline logs reconnect attempt within 60s
- [ ] No crash / zombie process
- [ ] MQTT heartbeats resume
- [ ] Buffered events replay in `occurred_at` order (check backend `queue_events`)

---

## 3. 100-crossing accuracy test

### Setup

1. Mark tripwire line on entry path only
2. Reset counters: restart edge with clean SQLite buffer
3. Station two observers: one counts physical crossings, one watches edge preview

### Procedure

| Step | Action |
|------|--------|
| 1 | Record start `queue_tail` from Redis or dashboard |
| 2 | Have 100 devotees cross tripwire one at a time (~30 min) |
| 3 | Record edge IN count from preview overlay |
| 4 | Compare manual count vs edge IN events |

Pass: **≥ 98/100** crossings detected (≤ 2% miss rate).

Log template:

```
date, location_id, manual_count, edge_in_count, false_positives, notes
```

---

## 4. FPS benchmark

Run included benchmark script on Jetson:

```bash
cd apps/edge
poetry run python scripts/benchmark_fps.py \
  --config=config/location_3.yaml \
  --duration-sec=120 \
  --output=/tmp/triosense-fps.json
```

Pass criteria (YOLOv8n + TensorRT, 1080p RTSP):

| Metric | Target |
|--------|--------|
| Mean inference FPS | ≥ 15 |
| p95 frame latency | ≤ 80 ms |
| Dropped frames | < 5% |

Review output:

```bash
jq '{mean_fps, p95_ms, dropped_pct}' /tmp/triosense-fps.json
```

---

## 5. MQTT end-to-end verification

After 100-crossing test:

```sql
SELECT event_type, COUNT(*)
FROM queue_events
WHERE location_id = 3 AND DATE(occurred_at) = CURDATE()
GROUP BY event_type;
```

Expect `enter` count ≈ manual crossing count.

---

## 6. Sign-off checklist

| Item | Pass |
|------|------|
| RTSP stable ≥ 4 hours continuous | ☐ |
| Reconnect after 30s outage | ☐ |
| 100-crossing ≥ 98% accuracy | ☐ |
| FPS benchmark meets targets | ☐ |
| SQLite buffer replay after MQTT outage | ☐ |
| Heartbeat every 30s on `triosense/loc/{id}/edge/{uid}/heartbeat` | ☐ |

---

## Troubleshooting

| Symptom | Action |
|---------|--------|
| TensorRT engine build fails | Re-export with matching JetPack TRT version |
| High false IN counts | Re-calibrate tripwire; check occlusion |
| RTSP stalls | Force TCP transport in GStreamer pipeline config |
| MQTT TLS errors | Verify client cert CN matches `edge_devices.device_uid` |

See also [`apps/edge/README.md`](../../apps/edge/README.md) and [`edge-offline.md`](./edge-offline.md).
