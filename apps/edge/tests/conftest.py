"""Shared pytest fixtures."""

from __future__ import annotations

from pathlib import Path

import pytest

from triosense_edge.config import CameraConfig, EdgeConfig, MqttConfig, TripwireConfig


@pytest.fixture
def sample_tripwire() -> TripwireConfig:
    return TripwireConfig(line=((640, 600), (1280, 600)), direction="down")


@pytest.fixture
def sample_edge_config(tmp_path: Path, sample_tripwire: TripwireConfig) -> EdgeConfig:
    return EdgeConfig(
        device_uid="edge-bdv-01",
        tenant_id=1,
        location_id=3,
        cameras=[
            CameraConfig(
                camera_id=17,
                name="Entry",
                role="entry_tripwire",
                rtsp_url="rtsp://127.0.0.1/entry",
                tripwire=sample_tripwire,
            )
        ],
        mqtt=MqttConfig(
            broker_host="127.0.0.1",
            broker_port=1883,
            client_id="edge-bdv-01",
            tls_enabled=False,
        ),
        inference_backend="mock",
        stream_backend="mock",
        buffer_db_path=tmp_path / "buffer.sqlite",
    )
