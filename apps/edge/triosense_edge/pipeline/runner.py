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
from triosense_edge.pipeline.types import Detection, Frame, Track
from triosense_edge.preview.annotate import annotate_frame, encode_jpeg
from triosense_edge.preview.state import PreviewState, PreviewStats
from triosense_edge.transport.mqtt_client import MqttClient
from triosense_edge.transport.schemas import CameraHeartbeat, EventPayload, HeartbeatPayload

log = logging.getLogger(__name__)


@dataclass
class CameraStats:
    inference_fps: float = 0.0
    preview_fps: float = 0.0
    last_frame_at: datetime | None = None
    status: str = "starting"


@dataclass
class _InferenceSnapshot:
    detections: list[Detection] = field(default_factory=list)
    tracks: list[Track] = field(default_factory=list)


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
            inference_width=self.config.inference_width,
        )
        log.info(
            "detector ready backend=%s model=%s inference_width=%d inference_fps=%d preview_fps=%d",
            self.config.inference_backend,
            self.config.model_path,
            self.config.inference_width,
            self.config.inference_fps,
            self.config.preview_fps,
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
            target_fps=self.config.preview_fps,
            reconnect_seconds=self.config.rtsp_reconnect_seconds,
            capture_width=self.config.capture_width,
            capture_height=self.config.capture_height,
        )
        stats = self._camera_stats[camera.camera_id]
        self._preview_counters.setdefault(camera.camera_id, (0, 0))

        latest_frame: Frame | None = None
        frame_lock = asyncio.Lock()
        inference_snapshot = _InferenceSnapshot()
        inference_interval = 1.0 / max(1, self.config.inference_fps)
        preview_interval = 1.0 / max(1, self.config.preview_fps)

        async def capture_loop() -> None:
            nonlocal latest_frame
            while True:
                try:
                    async with stream.connect():
                        stats.status = "ok"
                        log.info(
                            "capture loop started camera_id=%d preview_fps=%d",
                            camera.camera_id,
                            self.config.preview_fps,
                        )
                        async for frame in stream.frames():
                            async with frame_lock:
                                latest_frame = frame
                except asyncio.CancelledError:
                    stats.status = "stopped"
                    raise
                except Exception:
                    stats.status = "degraded"
                    log.exception("capture loop error camera_id=%d", camera.camera_id)
                    await asyncio.sleep(self.config.rtsp_reconnect_seconds)

        async def inference_loop() -> None:
            loop = asyncio.get_running_loop()
            while True:
                await asyncio.sleep(inference_interval)
                async with frame_lock:
                    frame = latest_frame
                if frame is None:
                    log.debug("inference skipped camera_id=%d — no frame yet", camera.camera_id)
                    continue

                loop_start = time.perf_counter()
                detections = await loop.run_in_executor(None, self._detector.detect, frame)  # type: ignore[attr-defined]
                tracks = tracker.update(detections)
                elapsed = max(time.perf_counter() - loop_start, 1e-6)
                stats.inference_fps = round(1.0 / elapsed, 2)
                stats.last_frame_at = frame.timestamp
                inference_snapshot.detections = detections
                inference_snapshot.tracks = tracks
                log.debug(
                    "inference tick camera_id=%d frame=%d detections=%d tracks=%d fps=%.2f",
                    camera.camera_id,
                    frame.frame_number,
                    len(detections),
                    len(tracks),
                    stats.inference_fps,
                )

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

        async def preview_loop() -> None:
            while True:
                await asyncio.sleep(preview_interval)
                async with frame_lock:
                    frame = latest_frame
                if frame is None:
                    continue
                await self._publish_preview_frame(
                    camera,
                    frame.image,
                    inference_snapshot.detections,
                    inference_snapshot.tracks,
                    stats,
                )

        tasks = [
            asyncio.create_task(capture_loop(), name=f"capture-{camera.camera_id}"),
            asyncio.create_task(inference_loop(), name=f"inference-{camera.camera_id}"),
        ]
        if self.preview_state is not None:
            tasks.append(
                asyncio.create_task(preview_loop(), name=f"preview-{camera.camera_id}"),
            )

        try:
            await asyncio.gather(*tasks)
        except asyncio.CancelledError:
            stats.status = "stopped"
            for task in tasks:
                task.cancel()
            await asyncio.gather(*tasks, return_exceptions=True)
            raise

    async def _publish_preview_frame(
        self,
        camera: CameraConfig,
        image: object,
        detections: list[Detection],
        tracks: list[Track],
        stats: CameraStats,
    ) -> None:
        if self.preview_state is None:
            return

        loop_start = time.perf_counter()
        enters, exits = self._preview_counters.get(camera.camera_id, (0, 0))
        preview_stats = PreviewStats(
            person_count=len(tracks),
            enter_count=enters,
            exit_count=exits,
            fps=stats.preview_fps,
            inference_fps=stats.inference_fps,
            preview_fps=stats.preview_fps,
            camera_id=camera.camera_id,
            status=stats.status,
        )
        annotated = annotate_frame(
            image,  # type: ignore[arg-type]
            detections=detections,
            tracks=tracks,
            tripwire=camera.tripwire,
            stats=preview_stats,
        )
        jpeg = encode_jpeg(
            annotated,
            quality=self.config.preview_jpeg_quality,
            max_width=self.config.preview_max_width,
        )
        elapsed = max(time.perf_counter() - loop_start, 1e-6)
        stats.preview_fps = round(1.0 / elapsed, 2)
        preview_stats.preview_fps = stats.preview_fps
        preview_stats.fps = stats.preview_fps
        await self.preview_state.update(jpeg, preview_stats)

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
                        fps=stats.inference_fps,
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
