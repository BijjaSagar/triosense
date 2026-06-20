"""YOLOv8 person detector with TensorRT/CPU/mock backends."""

from __future__ import annotations

import logging
from typing import Any, Literal, Protocol

import cv2
import numpy as np
from numpy.typing import NDArray

from triosense_edge.pipeline.types import Detection, Frame

log = logging.getLogger(__name__)

PERSON_CLASS_ID = 0


class Detector(Protocol):
    def detect(self, frame: Frame) -> list[Detection]: ...


def _resize_for_inference(
    image: NDArray[np.uint8],
    inference_width: int,
) -> tuple[NDArray[np.uint8], float]:
    height, width = image.shape[:2]
    if width <= inference_width:
        return image, 1.0
    scale = inference_width / width
    new_height = max(1, int(height * scale))
    resized = cv2.resize(image, (inference_width, new_height), interpolation=cv2.INTER_AREA)
    log.debug(
        "inference resize original=%dx%d scaled=%dx%d scale=%.3f",
        width,
        height,
        inference_width,
        new_height,
        scale,
    )
    return resized, scale


class YoloDetector:
    def __init__(
        self,
        *,
        model_path: str = "yolov8n.pt",
        backend: Literal["cpu", "tensorrt", "mock"] = "cpu",
        confidence_threshold: float = 0.5,
        inference_width: int = 640,
    ) -> None:
        self._backend = backend
        self._confidence_threshold = confidence_threshold
        self._inference_width = inference_width
        self._model: Any = None

        if backend == "mock":
            log.info("using mock detector inference_width=%d", inference_width)
            return

        from ultralytics import YOLO

        resolved_path = model_path
        if backend == "tensorrt" and not model_path.endswith(".engine"):
            resolved_path = model_path.replace(".pt", ".engine")
        log.info(
            "loading yolo model path=%s backend=%s inference_width=%d",
            resolved_path,
            backend,
            inference_width,
        )
        self._model = YOLO(resolved_path)

    def detect(self, frame: Frame) -> list[Detection]:
        if self._backend == "mock":
            return self._mock_detections(frame)

        if self._model is None:
            return []

        image, scale = _resize_for_inference(frame.image, self._inference_width)
        results = self._model.predict(
            image,
            classes=[PERSON_CLASS_ID],
            conf=self._confidence_threshold,
            verbose=False,
        )
        detections: list[Detection] = []
        inv_scale = 1.0 / scale
        for result in results:
            boxes = result.boxes
            if boxes is None:
                continue
            for box in boxes:
                xyxy = box.xyxy[0].tolist()
                conf = float(box.conf[0])
                cls_id = int(box.cls[0])
                x1, y1, x2, y2 = (int(v * inv_scale) for v in xyxy)
                detections.append(
                    Detection(
                        bbox=(x1, y1, x2, y2),
                        confidence=conf,
                        class_id=cls_id,
                    )
                )
        log.debug(
            "yolo detections frame=%d count=%d backend=%s",
            frame.frame_number,
            len(detections),
            self._backend,
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
    inference_width: int = 640,
) -> Detector:
    return YoloDetector(
        model_path=model_path,
        backend=backend,
        confidence_threshold=confidence_threshold,
        inference_width=inference_width,
    )
