# TrioSense Signage

A single static HTML file. No build step. No framework. Drop it onto the LED panel's display device and open it in a fullscreen browser.

## Run locally

```bash
cd apps/signage
python3 -m http.server 8090
# Then open http://localhost:8090/#location_id=3
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

## Deployment

- Boots into a kiosk-mode browser (Chromium `--kiosk --no-first-run --noerrdialogs`).
- Auto-reconnects on WebSocket failure (3-second backoff).
- Shows a "Reconnecting…" banner if no update arrived in >10 seconds.
- Cycles through Telugu, Tamil, Hindi, English every 8 seconds.

## Why no build step

The signage display device is unattended, hard to update, and runs for years. A single static HTML file is the most resilient artefact we can ship. There is no React, no bundler, no npm step. Edit it in any text editor.

## Read before editing

- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) §2.2 (`private-signage.{location_id}` channel)
