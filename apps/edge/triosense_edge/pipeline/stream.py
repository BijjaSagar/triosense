"""Video frame intake — RTSP, webcam, GStreamer, OpenCV, or mock for dev/CI."""

from __future__ import annotations

import asyncio
import logging
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from datetime import UTC, datetime
from typing import Literal

import cv2
import numpy as np
from numpy.typing import NDArray

from triosense_edge.pipeline.types import Frame

log = logging.getLogger(__name__)


def _gstreamer_pipeline(rtsp_url: str) -> str:
    return (
        f"rtspsrc location={rtsp_url} latency=100 ! "
        "decodebin ! videoconvert ! video/x-raw,format=BGR ! "
        "appsink drop=1 max-buffers=1 sync=false"
    )


class RtspStream:
    def __init__(
        self,
        rtsp_url: str,
        *,
        source_type: Literal["rtsp", "webcam"] = "rtsp",
        backend: Literal["gstreamer", "opencv", "mock"] = "opencv",
        target_fps: int = 15,
        reconnect_seconds: float = 5.0,
    ) -> None:
        self._rtsp_url = rtsp_url
        self._source_type = source_type
        self._backend = backend
        self._target_fps = target_fps
        self._reconnect_seconds = reconnect_seconds
        self._frame_number = 0
        self._capture: cv2.VideoCapture | None = None

    @asynccontextmanager
    async def connect(self) -> AsyncIterator[RtspStream]:
        try:
            await self._open_capture()
            yield self
        finally:
            await self._close_capture()

    async def frames(self) -> AsyncIterator[Frame]:
        interval = 1.0 / max(1, self._target_fps)
        while True:
            try:
                frame = await self._read_frame()
                if frame is None:
                    log.warning(
                        "rtsp read failed url=%s backend=%s — reconnecting in %.1fs",
                        self._mask_url(self._rtsp_url),
                        self._backend,
                        self._reconnect_seconds,
                    )
                    await self._close_capture()
                    await asyncio.sleep(self._reconnect_seconds)
                    await self._open_capture()
                    continue

                self._frame_number += 1
                yield Frame(
                    image=frame,
                    timestamp=datetime.now(UTC),
                    frame_number=self._frame_number,
                )
                if self._backend != "mock":
                    await asyncio.sleep(interval)
            except asyncio.CancelledError:
                raise
            except Exception:
                log.exception("stream loop error url=%s", self._mask_url(self._rtsp_url))
                await self._close_capture()
                await asyncio.sleep(self._reconnect_seconds)
                await self._open_capture()

    async def _open_capture(self) -> None:
        if self._backend == "mock":
            log.info("mock stream enabled url=%s", self._mask_url(self._rtsp_url))
            self._capture = None
            return

        loop = asyncio.get_running_loop()
        self._capture = await loop.run_in_executor(None, self._open_capture_sync)
        masked = self._mask_url(self._rtsp_url)
        if self._capture is None or not self._capture.isOpened():
            log.error("failed to open rtsp url=%s backend=%s", masked, self._backend)
        else:
            log.info("rtsp stream opened url=%s backend=%s", masked, self._backend)

    def _open_capture_sync(self) -> cv2.VideoCapture | None:
        if self._source_type == "webcam":
            device_index = self._webcam_device_index()
            log.info("opening webcam device_index=%d", device_index)
            capture = cv2.VideoCapture(device_index)
            return capture if capture.isOpened() else None

        if self._backend == "gstreamer":
            pipeline = _gstreamer_pipeline(self._rtsp_url)
            capture = cv2.VideoCapture(pipeline, cv2.CAP_GSTREAMER)
            if capture.isOpened():
                return capture
            log.warning("gstreamer pipeline failed — falling back to ffmpeg/opencv")

        capture = cv2.VideoCapture(self._rtsp_url)
        return capture if capture.isOpened() else None

    def _webcam_device_index(self) -> int:
        raw = self._rtsp_url.strip()
        if raw.startswith("webcam:"):
            raw = raw.split(":", 1)[1]
        try:
            return int(raw)
        except ValueError:
            log.warning("invalid webcam device index %r — defaulting to 0", self._rtsp_url)
            return 0

    async def _read_frame(self) -> NDArray[np.uint8] | None:
        if self._backend == "mock":
            return self._synthetic_frame()

        if self._capture is None or not self._capture.isOpened():
            return None

        loop = asyncio.get_running_loop()
        ok, image = await loop.run_in_executor(None, self._capture.read)
        if not ok or image is None:
            return None
        return np.asarray(image, dtype=np.uint8)

    def _synthetic_frame(self) -> NDArray[np.uint8]:
        height, width = 1080, 1920
        image = np.zeros((height, width, 3), dtype=np.uint8)
        x = 400
        y = 400 + (self._frame_number * 20) % 500
        cv2.rectangle(image, (x, y), (x + 80, y + 120), (255, 255, 255), -1)
        return image

    async def _close_capture(self) -> None:
        if self._capture is not None:
            loop = asyncio.get_running_loop()
            await loop.run_in_executor(None, self._capture.release)
            self._capture = None

    @staticmethod
    def _mask_url(url: str) -> str:
        if "@" not in url:
            return url
        prefix, suffix = url.split("@", 1)
        if "://" in prefix:
            scheme, _ = prefix.split("://", 1)
            return f"{scheme}://***@{suffix}"
        return f"***@{suffix}"
