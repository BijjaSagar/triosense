"""Local MJPEG preview server for Mac webcam demos."""

from __future__ import annotations

import asyncio
import logging
from typing import AsyncIterator

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
          `Persons: ${data.person_count} | IN: ${data.enter_count} | OUT: ${data.exit_count} | FPS: ${data.fps}`;
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
                "fps": snapshot.fps,
                "camera_id": snapshot.camera_id,
                "status": snapshot.status,
            }
        )

    @app.get("/stream.mjpg")
    async def stream_mjpg() -> StreamingResponse:
        async def generate() -> AsyncIterator[bytes]:
            boundary = b"frame"
            while True:
                jpeg, _ = await preview_state.snapshot()
                if jpeg:
                    yield (
                        b"--"
                        + boundary
                        + b"\r\nContent-Type: image/jpeg\r\n\r\n"
                        + jpeg
                        + b"\r\n"
                    )
                await asyncio.sleep(0.05)

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
        "fps": stats.fps,
        "camera_id": stats.camera_id,
        "status": stats.status,
    }
