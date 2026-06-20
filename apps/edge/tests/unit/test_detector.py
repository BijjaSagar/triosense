"""Unit tests for YOLO detector resize behaviour."""

from __future__ import annotations

from datetime import UTC, datetime

import numpy as np

from triosense_edge.pipeline.detector import YoloDetector, _resize_for_inference
from triosense_edge.pipeline.types import Frame


def test_resize_for_inference_scales_down_wide_frames() -> None:
    image = np.zeros((1080, 1920, 3), dtype=np.uint8)
    resized, scale = _resize_for_inference(image, inference_width=640)
    assert resized.shape[1] == 640
    assert scale == 640 / 1920


def test_mock_detector_finds_bright_region() -> None:
    detector = YoloDetector(backend="mock", inference_width=640)
    image = np.zeros((480, 640, 3), dtype=np.uint8)
    image[100:300, 200:300] = 255
    frame = Frame(image=image, timestamp=datetime.now(UTC), frame_number=1)
    detections = detector.detect(frame)
    assert len(detections) == 1
    assert detections[0].class_id == 0
