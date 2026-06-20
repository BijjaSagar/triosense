"""Local MJPEG preview server for Mac webcam demos."""

from __future__ import annotations

import asyncio
import logging
from collections.abc import AsyncIterator

import uvicorn
from fastapi import FastAPI
from fastapi.responses import HTMLResponse, JSONResponse, StreamingResponse

from triosense_edge.preview.state import PreviewState, PreviewStats

log = logging.getLogger(__name__)

INDEX_HTML = """<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>TrioSense Edge Preview</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 1rem; background: #111; color: #eee; }
    img { max-width: 100%; border: 1px solid #444; }
    .stats { margin-top: 0.75rem; font-size: 1.1rem; }
  </style>
</head>
<body>
  <h1>TrioSense edge preview</h1>
  <p>Live webcam with person detection and tripwire counters.</p>
  <img src="/stream.mjpg" alt="Live edge preview" />
  <div class="stats" id="stats">Loading stats…</div>
  <script>
    async function refreshStats() {
      try {
        const res = await fetch('/api/stats');
        const data = await res.json();
        document.getElementById('stats').textContent =
          `Persons: ${data.person_count} | IN: ${data.enter_count} | OUT: ${data.exit_count}`
          + ` | Preview: ${data.preview_fps} FPS | Inference: ${data.inference_fps} FPS`;
      } catch (err) {
        document.getElementById('stats').textContent = 'Stats unavailable';
      }
    }
    setInterval(refreshStats, 1000);
    refreshStats();
  </script>
</body>
</html>"""


def create_app(preview_state: PreviewState) -> FastAPI:
    app = FastAPI(title="TrioSense Edge Preview")

    @app.get("/", response_class=HTMLResponse)
    async def index() -> str:
        return INDEX_HTML

    @app.get("/api/stats")
    async def stats() -> JSONResponse:
        _, snapshot = await preview_state.snapshot()
        return JSONResponse(
            {
                "person_count": snapshot.person_count,
                "enter_count": snapshot.enter_count,
                "exit_count": snapshot.exit_count,
                "fps": snapshot.preview_fps,
                "preview_fps": snapshot.preview_fps,
                "inference_fps": snapshot.inference_fps,
                "camera_id": snapshot.camera_id,
                "status": snapshot.status,
            }
        )

    @app.get("/stream.mjpg")
    async def stream_mjpg() -> StreamingResponse:
        async def generate() -> AsyncIterator[bytes]:
            boundary = b"frame"
            while True:
                jpeg, snapshot = await preview_state.snapshot()
                if jpeg:
                    yield (
                        b"--"
                        + boundary
                        + b"\r\nContent-Type: image/jpeg\r\n\r\n"
                        + jpeg
                        + b"\r\n"
                    )
                interval = 1.0 / max(snapshot.preview_fps, 1.0)
                await asyncio.sleep(min(interval, 0.1))

        return StreamingResponse(
            generate(),
            media_type="multipart/x-mixed-replace; boundary=frame",
        )

    return app


async def run_preview_server(
    preview_state: PreviewState,
    *,
    host: str = "127.0.0.1",
    port: int = 8766,
    log_level: str = "info",
) -> None:
    app = create_app(preview_state)
    config = uvicorn.Config(
        app,
        host=host,
        port=port,
        log_level=log_level.lower(),
        access_log=False,
    )
    server = uvicorn.Server(config)
    log.info("preview server listening on http://%s:%d", host, port)
    await server.serve()


def stats_payload(stats: PreviewStats) -> dict[str, float | int | str]:
    return {
        "person_count": stats.person_count,
        "enter_count": stats.enter_count,
        "exit_count": stats.exit_count,
        "fps": stats.preview_fps,
        "preview_fps": stats.preview_fps,
        "inference_fps": stats.inference_fps,
        "camera_id": stats.camera_id,
        "status": stats.status,
    }
