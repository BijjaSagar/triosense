# TrioSense Signage

A single static HTML file. No build step. No framework. Drop it onto the LED panel's display device and open it in a fullscreen browser.

## Run locally

```bash
cd apps/signage
python3 -m http.server 8090
# Then open http://localhost:8090/#location_id=3&debug=1
```

## URL hash parameters

Override defaults via the URL hash (so a single index.html works at all three counters):

```
index.html#location_id=3&host=ws.triosense.in&port=443&scheme=wss&key=PROD_REVERB_KEY
```

| Param | Default | Notes |
| --- | --- | --- |
| `location_id` | `3` | Which location to subscribe to |
| `host` | current hostname | Reverb WebSocket host |
| `port` | `8080` | Reverb port |
| `scheme` | `ws` | `wss` in production |
| `key` | `trioseanse-local-key` | Reverb app key |
| `debug` | off | Set `debug=1` for console logging |

## WebSocket channel

Subscribes to `private-location.{location_id}` and listens for `LocationStateUpdated` events per [`API_CONTRACTS.md`](../../API_CONTRACTS.md) §2.2.

For production private channels, configure Reverb auth on the signage device (Sanctum token in subscribe handshake) or use a dedicated signage service account.

## HDMI / LED panel testing

1. Connect the signage PC (industrial Android player or x86 SBC) to the outdoor LED controller via **HDMI**.
2. Set display output to native panel resolution (typically 1920×1080 or 1280×720).
3. Open Chromium in kiosk mode: `chromium --kiosk --no-first-run file:///opt/triosense/signage/index.html#location_id=3`
4. Verify from the dashboard that live token counts update on the panel within 1 second of a simulated event.
5. Disconnect network — confirm stale banner appears after 10 seconds and static fallback messaging is readable at 5m viewing distance.

## Deployment

- Boots into a kiosk-mode browser (Chromium `--kiosk --no-first-run --noerrdialogs`).
- Auto-reconnects on WebSocket failure (3-second backoff).
- Shows a "Reconnecting…" banner if no update arrived in >10 seconds.
- Cycles through Telugu, Tamil, Hindi, English every 8 seconds.

## Why no build step

The signage display device is unattended, hard to update, and runs for years. A single static HTML file is the most resilient artefact we can ship. There is no React, no bundler, no npm step. Edit it in any text editor.

## Read before editing

- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) §2.2 (`private-location.{location_id}` channel)
