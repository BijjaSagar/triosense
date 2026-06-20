"""Local web UI for drawing tripwire lines on streamed frames."""

from __future__ import annotations

import argparse
import asyncio
import base64
import logging
from pathlib import Path

import cv2
import uvicorn
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, JSONResponse

from triosense_edge.config import EdgeConfig, TripwireConfig
from triosense_edge.pipeline.stream import RtspStream

log = logging.getLogger(__name__)

INDEX_HTML = """<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>TrioSense Tripwire Calibration</title>
  <style>
    body { font-family: sans-serif; margin: 1rem; }
    #canvas { border: 1px solid #333; cursor: crosshair; max-width: 100%; }
    .row { margin: 0.5rem 0; }
  </style>
</head>
<body>
  <h1>Tripwire calibration</h1>
  <p>Click two points on the frame to define the tripwire line.</p>
  <div class="row">
    <label>Direction:
      <select id="direction">
        <option value="down">down (IN)</option>
        <option value="up">up</option>
        <option value="left">left</option>
        <option value="right">right</option>
      </select>
    </label>
    <button id="save">Save to config YAML</button>
    <button id="clear">Clear points</button>
  </div>
  <canvas id="canvas" width="1280" height="720"></canvas>
  <pre id="output"></pre>
  <script>
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const points = [];
    let frameImage = new Image();

    async function refreshFrame() {
      const res = await fetch('/api/frame');
      const data = await res.json();
      frameImage = new Image();
      frameImage.onload = () => {
        canvas.width = frameImage.width;
        canvas.height = frameImage.height;
        draw();
      };
      frameImage.src = 'data:image/jpeg;base64,' + data.image_b64;
    }

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (frameImage.src) ctx.drawImage(frameImage, 0, 0);
      ctx.strokeStyle = '#00ff88';
      ctx.lineWidth = 3;
      if (points.length === 2) {
        ctx.beginPath();
        ctx.moveTo(points[0][0], points[0][1]);
        ctx.lineTo(points[1][0], points[1][1]);
        ctx.stroke();
      }
      ctx.fillStyle = '#ff3366';
      points.forEach(([x, y]) => { ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2); ctx.fill(); });
    }

    canvas.addEventListener('click', (ev) => {
      const rect = canvas.getBoundingClientRect();
      const x = Math.round((ev.clientX - rect.left) * (canvas.width / rect.width));
      const y = Math.round((ev.clientY - rect.top) * (canvas.height / rect.height));
      if (points.length >= 2) points.length = 0;
      points.push([x, y]);
      draw();
      document.getElementById('output').textContent = JSON.stringify(points, null, 2);
    });

    document.getElementById('clear').onclick = () => { points.length = 0; draw(); };
    document.getElementById('save').onclick = async () => {
      if (points.length !== 2) { alert('Select two points first'); return; }
      const body = {
        line: points,
        direction: document.getElementById('direction').value,
        camera_index: 0,
      };
      const res = await fetch('/api/tripwire', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const data = await res.json();
      alert(data.message || 'saved');
    };

    setInterval(refreshFrame, 2000);
    refreshFrame();
  </script>
</body>
</html>"""


def create_app(config_path: Path, camera_index: int = 0) -> FastAPI:
    app = FastAPI(title="TrioSense Calibration")
    edge_config = EdgeConfig.from_yaml(config_path)
    camera = edge_config.cameras[camera_index]
    stream = RtspStream(
        camera.rtsp_url,
        source_type=camera.source_type,
        backend=edge_config.stream_backend,
        target_fps=2,
        reconnect_seconds=edge_config.rtsp_reconnect_seconds,
    )
    latest_jpeg: dict[str, bytes] = {"data": b""}

    async def _capture_loop() -> None:
        async with stream.connect():
            async for frame in stream.frames():
                ok, encoded = cv2.imencode(".jpg", frame.image)
                if ok:
                    latest_jpeg["data"] = encoded.tobytes()

    @app.on_event("startup")
    async def startup() -> None:
        asyncio.create_task(_capture_loop(), name="calibration-capture")
        log.info("calibration server started camera_id=%d", camera.camera_id)

    @app.get("/", response_class=HTMLResponse)
    async def index() -> str:
        return INDEX_HTML

    @app.get("/api/frame")
    async def frame() -> JSONResponse:
        if not latest_jpeg["data"]:
            import numpy as np

            image = np.zeros((720, 1280, 3), dtype=np.uint8)
            ok, encoded = cv2.imencode(".jpg", image)
            payload = encoded.tobytes() if ok else b""
        else:
            payload = latest_jpeg["data"]
        return JSONResponse({"image_b64": base64.b64encode(payload).decode("ascii")})

    @app.post("/api/tripwire")
    async def save_tripwire(request: Request) -> JSONResponse:
        body = await request.json()
        line = body.get("line")
        direction = body.get("direction", "down")
        if not isinstance(line, list) or len(line) != 2:
            return JSONResponse(
                {"success": False, "message": "line must have two points"},
                status_code=400,
            )
        tripwire = TripwireConfig.model_validate({"line": line, "direction": direction})
        idx = int(body.get("camera_index", camera_index))
        edge_config.cameras[idx].tripwire = tripwire
        edge_config.to_yaml(config_path)
        log.info("saved tripwire camera_id=%d line=%s", edge_config.cameras[idx].camera_id, line)
        return JSONResponse({"success": True, "message": f"Saved tripwire to {config_path}"})

    return app


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="triosense-edge-calibrate")
    parser.add_argument("--config", type=Path, required=True)
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8765)
    parser.add_argument("--camera-index", type=int, default=0)
    parser.add_argument("--log-level", default="INFO")
    args = parser.parse_args(argv)
    logging.basicConfig(level=getattr(logging, args.log_level))
    app = create_app(args.config, camera_index=args.camera_index)
    uvicorn.run(app, host=args.host, port=args.port, log_level=args.log_level.lower())
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
