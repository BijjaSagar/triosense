"""Shared preview frame + counter state for the local demo HTTP server."""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass, field

log = logging.getLogger(__name__)


@dataclass
class PreviewStats:
    person_count: int = 0
    enter_count: int = 0
    exit_count: int = 0
    fps: float = 0.0
    inference_fps: float = 0.0
    preview_fps: float = 0.0
    camera_id: int = 0
    status: str = "starting"


@dataclass
class PreviewState:
    """Thread-safe-ish store for the latest annotated JPEG and overlay stats."""

    _lock: asyncio.Lock = field(default_factory=asyncio.Lock)
    _jpeg: bytes = b""
    stats: PreviewStats = field(default_factory=PreviewStats)

    async def update(self, jpeg: bytes, stats: PreviewStats) -> None:
        async with self._lock:
            self._jpeg = jpeg
            self.stats = stats
            log.debug(
                "preview frame updated camera_id=%d persons=%d enters=%d exits=%d "
                "preview_fps=%.1f inference_fps=%.1f",
                stats.camera_id,
                stats.person_count,
                stats.enter_count,
                stats.exit_count,
                stats.preview_fps,
                stats.inference_fps,
            )

    async def snapshot(self) -> tuple[bytes, PreviewStats]:
        async with self._lock:
            return self._jpeg, self.stats
