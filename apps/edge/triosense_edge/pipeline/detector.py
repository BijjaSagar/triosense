"""YOLOv8 person detector with TensorRT/CPU/mock backends."""

from __future__ import annotations

import logging
from typing import Any, Literal, Protocol

import numpy as np
from numpy.typing import NDArray

from triosense_edge.pipeline.types import Detection, Frame

log = logging.getLogger(__name__)

PERSON_CLASS_ID = 0


class Detector(Protocol):
    def detect(self, frame: Frame) -> list[Detection]: ...


class YoloDetector:
    def __init__(
        self,
        *,
        model_path: str = "yolov8n.pt",
        backend: Literal["cpu", "tensorrt", "mock"] = "cpu",
        confidence_threshold: float = 0.5,
    ) -> None:
        self._backend = backend
        self._confidence_threshold = confidence_threshold
        self._model: Any = None

        if backend == "mock":
            log.info("using mock detector")
            return

        from ultralytics import YOLO

        resolved_path = model_path
        if backend == "tensorrt" and not model_path.endswith(".engine"):
            resolved_path = model_path.replace(".pt", ".engine")
        log.info("loading yolo model path=%s backend=%s", resolved_path, backend)
        self._model = YOLO(resolved_path)

    def detect(self, frame: Frame) -> list[Detection]:
        if self._backend == "mock":
            return self._mock_detections(frame)

        if self._model is None:
            return []

        results = self._model.predict(
            frame.image,
            classes=[PERSON_CLASS_ID],
            conf=self._confidence_threshold,
            verbose=False,
        )
        detections: list[Detection] = []
        for result in results:
            boxes = result.boxes
            if boxes is None:
                continue
            for box in boxes:
                xyxy = box.xyxy[0].tolist()
                conf = float(box.conf[0])
                cls_id = int(box.cls[0])
                x1, y1, x2, y2 = (int(v) for v in xyxy)
                detections.append(
                    Detection(
                        bbox=(x1, y1, x2, y2),
                        confidence=conf,
                        class_id=cls_id,
                    )
                )
        return detections

    def _mock_detections(self, frame: Frame) -> list[Detection]:
        image: NDArray[np.uint8] = frame.image
        gray = image.mean(axis=2)
        ys, xs = np.where(gray > 50)
        if len(xs) == 0:
            return []
        x1, x2 = int(xs.min()), int(xs.max())
        y1, y2 = int(ys.min()), int(ys.max())
        return [
            Detection(
                bbox=(x1, y1, x2, y2),
                confidence=0.92,
                class_id=PERSON_CLASS_ID,
            )
        ]


def build_detector(
    *,
    model_path: str,
    backend: Literal["cpu", "tensorrt", "mock"],
    confidence_threshold: float,
) -> Detector:
    return YoloDetector(
        model_path=model_path,
        backend=backend,
        confidence_threshold=confidence_threshold,
    )
