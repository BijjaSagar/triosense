"""Tests for webcam config and stream source handling."""

from __future__ import annotations

from pathlib import Path

from triosense_edge.config import EdgeConfig
from triosense_edge.pipeline.stream import RtspStream


def test_load_local_webcam_yaml() -> None:
    path = Path(__file__).resolve().parents[2] / "config" / "local.webcam.yaml"
    config = EdgeConfig.from_yaml(path)
    assert config.location_id == 1
    assert config.cameras[0].source_type == "webcam"
    assert config.cameras[0].rtsp_url == "0"
    assert config.mqtt.tls_enabled is False
    assert config.inference_fps == 4
    assert config.preview_fps == 12
    assert config.capture_width == 640
    assert config.capture_height == 480
    assert config.inference_width == 640
    assert config.preview_jpeg_quality == 70
    assert config.preview_max_width == 640
    assert config.model_path == "yolov8n.pt"


def test_webcam_device_index_parsing() -> None:
    stream = RtspStream("webcam:2", source_type="webcam")
    assert stream._webcam_device_index() == 2

    stream_zero = RtspStream("0", source_type="webcam")
    assert stream_zero._webcam_device_index() == 0


def test_webcam_stream_accepts_capture_dimensions() -> None:
    stream = RtspStream(
        "0",
        source_type="webcam",
        capture_width=640,
        capture_height=480,
        target_fps=12,
    )
    assert stream._capture_width == 640
    assert stream._capture_height == 480
    assert stream._target_fps == 12
