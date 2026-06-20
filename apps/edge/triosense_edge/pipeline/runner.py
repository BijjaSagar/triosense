"""Pipeline orchestrator — one asyncio task per camera."""

from __future__ import annotations

import asyncio
import logging
import time
from dataclasses import dataclass, field
from datetime import UTC, datetime

from triosense_edge.config import CameraConfig, EdgeConfig
from triosense_edge.pipeline.detector import build_detector
from triosense_edge.pipeline.stream import RtspStream
from triosense_edge.pipeline.tracker import ByteTracker
from triosense_edge.pipeline.tripwire import TripwireDetector
from triosense_edge.preview.annotate import annotate_frame, encode_jpeg
from triosense_edge.preview.state import PreviewState, PreviewStats
from triosense_edge.transport.mqtt_client import MqttClient
from triosense_edge.transport.schemas import CameraHeartbeat, EventPayload, HeartbeatPayload

log = logging.getLogger(__name__)


@dataclass
class CameraStats:
    fps: float = 0.0
    last_frame_at: datetime | None = None
    status: str = "starting"


@dataclass
class PipelineRunner:
    config: EdgeConfig
    mqtt: MqttClient
    preview_state: PreviewState | None = None
    _detector: object = field(init=False)
    _tasks: list[asyncio.Task[None]] = field(default_factory=list)
    _camera_stats: dict[int, CameraStats] = field(default_factory=dict)
    _preview_counters: dict[int, tuple[int, int]] = field(default_factory=dict)
    _started_at: datetime = field(default_factory=lambda: datetime.now(UTC))

    def __post_init__(self) -> None:
        self._detector = build_detector(
            model_path=self.config.model_path,
            backend=self.config.inference_backend,
            confidence_threshold=self.config.inference_confidence_threshold,
        )

    async def start(self) -> None:
        for camera in self.config.cameras:
            self._camera_stats[camera.camera_id] = CameraStats()
            task = asyncio.create_task(
                self._process_camera(camera),
                name=f"camera-{camera.camera_id}",
            )
            self._tasks.append(task)
        heartbeat = asyncio.create_task(self._heartbeat_loop(), name="heartbeat")
        self._tasks.append(heartbeat)
        log.info(
            "pipeline started device=%s cameras=%d",
            self.config.device_uid,
            len(self.config.cameras),
        )

    async def stop(self) -> None:
        for task in self._tasks:
            task.cancel()
        await asyncio.gather(*self._tasks, return_exceptions=True)
        self._tasks.clear()
        log.info("pipeline stopped device=%s", self.config.device_uid)

    async def _process_camera(self, camera: CameraConfig) -> None:
        tracker = ByteTracker()
        tripwire = TripwireDetector(camera.tripwire) if camera.tripwire else None
        stream = RtspStream(
            camera.rtsp_url,
            source_type=camera.source_type,
            backend=self.config.stream_backend,
            target_fps=self.config.inference_fps,
            reconnect_seconds=self.config.rtsp_reconnect_seconds,
        )
        stats = self._camera_stats[camera.camera_id]
        self._preview_counters.setdefault(camera.camera_id, (0, 0))

        while True:
            try:
                async with stream.connect():
                    stats.status = "ok"
                    async for frame in stream.frames():
                        loop_start = time.perf_counter()
                        detections = self._detector.detect(frame)  # type: ignore[attr-defined]
                        tracks = tracker.update(detections)
                        stats.last_frame_at = frame.timestamp
                        elapsed = max(time.perf_counter() - loop_start, 1e-6)
                        stats.fps = round(1.0 / elapsed, 2)

                        if tripwire is not None:
                            for event in tripwire.process(tracks, frame.timestamp):
                                enters, exits = self._preview_counters[camera.camera_id]
                                if event.direction == "in":
                                    enters += 1
                                else:
                                    exits += 1
                                self._preview_counters[camera.camera_id] = (enters, exits)
                                await self._publish_tripwire_event(
                                    camera,
                                    event,
                                    frame.frame_number,
                                )

                        await self._publish_preview_frame(
                            camera,
                            frame.image,
                            detections,
                            tracks,
                            stats,
                        )
            except asyncio.CancelledError:
                stats.status = "stopped"
                raise
            except Exception:
                stats.status = "degraded"
                log.exception("camera loop error camera_id=%d", camera.camera_id)
                await asyncio.sleep(self.config.rtsp_reconnect_seconds)

    async def _publish_preview_frame(
        self,
        camera: CameraConfig,
        image: object,
        detections: list[object],
        tracks: list[object],
        stats: CameraStats,
    ) -> None:
        if self.preview_state is None:
            return

        from triosense_edge.pipeline.types import Detection, Track

        typed_detections = [item for item in detections if isinstance(item, Detection)]
        typed_tracks = [item for item in tracks if isinstance(item, Track)]
        enters, exits = self._preview_counters.get(camera.camera_id, (0, 0))
        preview_stats = PreviewStats(
            person_count=len(typed_tracks),
            enter_count=enters,
            exit_count=exits,
            fps=stats.fps,
            camera_id=camera.camera_id,
            status=stats.status,
        )
        annotated = annotate_frame(
            image,  # type: ignore[arg-type]
            detections=typed_detections,
            tracks=typed_tracks,
            tripwire=camera.tripwire,
            stats=preview_stats,
        )
        await self.preview_state.update(encode_jpeg(annotated), preview_stats)

    async def _publish_tripwire_event(
        self,
        camera: CameraConfig,
        event: object,
        frame_number: int,
    ) -> None:
        from triosense_edge.pipeline.tripwire import TripwireEvent

        if not isinstance(event, TripwireEvent):
            return

        topic_suffix = "enter" if event.direction == "in" else "exit"
        topic = (
            f"{self.mqtt.topic_prefix}/loc/{self.config.location_id}/event/{topic_suffix}"
        )
        payload = EventPayload(
            device_uid=self.config.device_uid,
            camera_id=camera.camera_id,
            occurred_at=event.occurred_at.isoformat(timespec="milliseconds").replace("+00:00", "Z"),
            track_id=event.track_id,
            confidence=event.confidence,
            metadata={"frame_number": frame_number, "bbox": list(event.bbox)},
        )
        await self.mqtt.publish(topic, payload, qos=1)
        log.info(
            "published %s camera_id=%d track=%s",
            topic_suffix,
            camera.camera_id,
            event.track_id,
        )

    async def _heartbeat_loop(self) -> None:
        topic = (
            f"{self.mqtt.topic_prefix}/loc/{self.config.location_id}"
            f"/edge/{self.config.device_uid}/heartbeat"
        )
        while True:
            await asyncio.sleep(self.config.heartbeat_seconds)
            cameras = []
            for camera in self.config.cameras:
                stats = self._camera_stats.get(camera.camera_id, CameraStats())
                cameras.append(
                    CameraHeartbeat(
                        camera_id=camera.camera_id,
                        status=stats.status,
                        fps=stats.fps,
                        last_frame_at=(
                            stats.last_frame_at.isoformat(timespec="milliseconds").replace(
                                "+00:00", "Z"
                            )
                            if stats.last_frame_at
                            else ""
                        ),
                    )
                )
            uptime = int((datetime.now(UTC) - self._started_at).total_seconds())
            buffer_size = await self.mqtt.buffered_count()
            payload = HeartbeatPayload(
                device_uid=self.config.device_uid,
                uptime_seconds=uptime,
                cpu_percent=0.0,
                mem_percent=0.0,
                temp_celsius=0.0,
                cameras=cameras,
                buffer_size=buffer_size,
            )
            await self.mqtt.publish(topic, payload, qos=0)
