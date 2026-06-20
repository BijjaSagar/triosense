"""Pydantic configuration models for the TrioSense edge service."""

from __future__ import annotations

from pathlib import Path
from typing import Literal

import yaml
from pydantic import BaseModel, Field, field_validator


class TripwireConfig(BaseModel):
    line: tuple[tuple[int, int], tuple[int, int]]
    direction: Literal["up", "down", "left", "right"]

    @field_validator("line", mode="before")
    @classmethod
    def _normalize_line(cls, value: object) -> tuple[tuple[int, int], tuple[int, int]]:
        if not isinstance(value, list | tuple) or len(value) != 2:
            msg = "tripwire line must be two [x, y] points"
            raise ValueError(msg)
        p1, p2 = value
        if not isinstance(p1, list | tuple) or not isinstance(p2, list | tuple):
            raise ValueError(msg)
        return ((int(p1[0]), int(p1[1])), (int(p2[0]), int(p2[1])))


class CameraConfig(BaseModel):
    camera_id: int
    name: str
    role: Literal["entry_tripwire", "counter_window", "density", "overview"]
    rtsp_url: str
    tripwire: TripwireConfig | None = None


class MqttConfig(BaseModel):
    broker_host: str
    broker_port: int = 8883
    client_id: str
    topic_prefix: str = "triosense"
    tls_enabled: bool = True
    tls_cert_path: Path | None = None
    tls_key_path: Path | None = None
    tls_ca_path: Path | None = None


class EdgeConfig(BaseModel):
    device_uid: str = Field(pattern=r"^edge-[a-z]{3}-\d{2}$")
    tenant_id: int
    location_id: int
    cameras: list[CameraConfig]
    mqtt: MqttConfig
    heartbeat_seconds: int = 5
    inference_fps: int = 15
    inference_confidence_threshold: float = Field(0.5, ge=0.0, le=1.0)
    inference_backend: Literal["cpu", "tensorrt", "mock"] = "cpu"
    stream_backend: Literal["gstreamer", "opencv", "mock"] = "opencv"
    buffer_db_path: Path = Path("/var/lib/triosense/buffer.sqlite")
    model_path: str = "yolov8n.pt"
    rtsp_reconnect_seconds: float = 5.0

    @classmethod
    def from_yaml(cls, path: Path) -> EdgeConfig:
        with path.open(encoding="utf-8") as handle:
            raw = yaml.safe_load(handle)
        if not isinstance(raw, dict):
            msg = f"invalid YAML config at {path}"
            raise ValueError(msg)
        return cls.model_validate(raw)

    def to_yaml(self, path: Path) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        payload = self.model_dump(mode="json")
        with path.open("w", encoding="utf-8") as handle:
            yaml.safe_dump(payload, handle, sort_keys=False)
