"""Tests for preview annotation helpers."""

from __future__ import annotations

import numpy as np

from triosense_edge.config import TripwireConfig
from triosense_edge.pipeline.types import Detection, Track
from triosense_edge.preview.annotate import _resize_for_preview, annotate_frame, encode_jpeg
from triosense_edge.preview.state import PreviewStats


def test_annotate_frame_draws_overlay() -> None:
    image = np.zeros((480, 640, 3), dtype=np.uint8)
    stats = PreviewStats(
        person_count=1,
        enter_count=2,
        exit_count=1,
        fps=4.5,
        inference_fps=3.2,
        preview_fps=4.5,
        camera_id=1,
    )
    tripwire = TripwireConfig(line=((100, 240), (540, 240)), direction="down")
    tracks = [
        Track(
            track_id="t-1",
            bbox=(120, 100, 180, 220),
            confidence=0.91,
        )
    ]
    detections = [Detection(bbox=(120, 100, 180, 220), confidence=0.91, class_id=0)]

    annotated = annotate_frame(
        image,
        detections=detections,
        tracks=tracks,
        tripwire=tripwire,
        stats=stats,
    )
    jpeg = encode_jpeg(annotated, quality=70, max_width=640)

    assert annotated.shape == image.shape
    assert annotated.sum() > 0
    assert len(jpeg) > 100


def test_encode_jpeg_downscales_wide_frames() -> None:
    image = np.zeros((1080, 1920, 3), dtype=np.uint8)
    resized = _resize_for_preview(image, max_width=640)
    assert resized.shape[1] == 640
    jpeg = encode_jpeg(image, quality=70, max_width=640)
    assert len(jpeg) > 100


async def test_preview_state_update_snapshot() -> None:
    from triosense_edge.preview.state import PreviewState

    state = PreviewState()
    stats = PreviewStats(
        person_count=3,
        enter_count=1,
        exit_count=0,
        fps=5.0,
        inference_fps=4.0,
        preview_fps=5.0,
        camera_id=1,
    )
    await state.update(b"jpeg-bytes", stats)

    jpeg, snapshot = await state.snapshot()
    assert jpeg == b"jpeg-bytes"
    assert snapshot.person_count == 3
    assert snapshot.inference_fps == 4.0
    assert snapshot.preview_fps == 5.0
