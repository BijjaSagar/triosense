# Edge failure runbook

## Symptoms
- Dashboard shows edge device offline or degraded
- No ENTER/EXIT/ISSUE events for >2 minutes
- Heartbeat key stale in Redis

## Diagnosis
1. Check edge device heartbeat: `GET /api/v1/locations/{id}/state` → `edge_devices[].last_heartbeat_at`
2. SSH to Jetson (if reachable): `journalctl -u triosense-edge -n 100`
3. Verify MQTT broker connectivity from edge VLAN

## Recovery
1. Restart edge service: `sudo systemctl restart triosense-edge`
2. If RTSP failure: check camera power and network; edge auto-reconnects in <10s
3. If MQTT TLS cert expired: redeploy certs from PKI runbook
4. Edge buffers events in SQLite for up to 24h — no manual replay needed on reconnect

## Target recovery time
- Automatic RTSP reconnect: <10 seconds
- Service restart: <90 seconds (boot SLA)
