"""MQTT payload schemas (Pydantic v2)."""

from __future__ import annotations

from datetime import UTC, datetime
from typing import Any

from pydantic import BaseModel, Field


def utc_now_iso() -> str:
    return datetime.now(UTC).isoformat(timespec="milliseconds").replace("+00:00", "Z")


class EventPayload(BaseModel):
    v: int = 1
    device_uid: str
    camera_id: int
    occurred_at: str = Field(default_factory=utc_now_iso)
    track_id: str
    confidence: float = Field(ge=0.0, le=1.0)
    metadata: dict[str, Any] = Field(default_factory=dict)


class CameraHeartbeat(BaseModel):
    camera_id: int
    status: str
    fps: float
    last_frame_at: str


class HeartbeatPayload(BaseModel):
    v: int = 1
    device_uid: str
    timestamp: str = Field(default_factory=utc_now_iso)
    uptime_seconds: int
    cpu_percent: float
    mem_percent: float
    temp_celsius: float
    cameras: list[CameraHeartbeat]
    buffer_size: int = 0
