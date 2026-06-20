"""Integration tests for pipeline components with mocks."""

from __future__ import annotations

from datetime import UTC, datetime

import numpy as np
import pytest

from triosense_edge.pipeline.detector import build_detector
from triosense_edge.pipeline.runner import PipelineRunner
from triosense_edge.pipeline.stream import RtspStream
from triosense_edge.pipeline.tracker import ByteTracker
from triosense_edge.pipeline.tripwire import TripwireDetector
from triosense_edge.pipeline.types import Frame
from triosense_edge.transport.buffer import EventBuffer
from triosense_edge.transport.mqtt_client import MqttClient


@pytest.mark.asyncio
async def test_mock_stream_produces_frames() -> None:
    stream = RtspStream("rtsp://mock", backend="mock", target_fps=30)
    async with stream.connect():
        frames = []
        async for frame in stream.frames():
            frames.append(frame)
            if len(frames) >= 3:
                break
    assert len(frames) == 3
    assert frames[0].image.shape == (1080, 1920, 3)


@pytest.mark.asyncio
async def test_mock_detector_finds_blob() -> None:
    detector = build_detector(model_path="yolov8n.pt", backend="mock", confidence_threshold=0.5)
    image = np.zeros((1080, 1920, 3), dtype=np.uint8)
    image[500:700, 400:500] = 255
    frame = Frame(image=image, timestamp=datetime.now(UTC), frame_number=1)
    detections = detector.detect(frame)
    assert len(detections) == 1


@pytest.mark.asyncio
async def test_pipeline_publishes_enter_on_mock_crossing(
    sample_edge_config,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    published: list[tuple[str, str]] = []

    async def fake_publish(topic: str, payload, qos: int = 1) -> bool:  # type: ignore[no-untyped-def]
        published.append((topic, payload.track_id))
        return True

    buffer = EventBuffer(sample_edge_config.buffer_db_path)
    await buffer.initialize()
    mqtt = MqttClient(sample_edge_config.mqtt, buffer)
    mqtt.publish = fake_publish  # type: ignore[method-assign]
    mqtt._connected.set()  # noqa: SLF001

    runner = PipelineRunner(config=sample_edge_config, mqtt=mqtt)
    camera = sample_edge_config.cameras[0]
    tracker = ByteTracker()
    tripwire = TripwireDetector(camera.tripwire)  # type: ignore[arg-type]

    stream = RtspStream(camera.rtsp_url, backend="mock", target_fps=60)
    async with stream.connect():
        frame_count = 0
        async for frame in stream.frames():
            frame_count += 1
            detections = runner._detector.detect(frame)  # noqa: SLF001
            tracks = tracker.update(detections)
            for event in tripwire.process(tracks, frame.timestamp):
                await runner._publish_tripwire_event(camera, event, frame.frame_number)  # noqa: SLF001
            if published or frame_count >= 30:
                break

    assert published
    assert published[0][0].endswith("/event/enter")
