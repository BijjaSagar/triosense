"""ByteTrack-style multi-object tracker (IoU association for CI-friendly tests)."""

from __future__ import annotations

import logging
from dataclasses import dataclass

from triosense_edge.pipeline.types import Detection, Track

log = logging.getLogger(__name__)


@dataclass
class _InternalTrack:
    track_id: str
    bbox: tuple[int, int, int, int]
    confidence: float
    missed: int = 0


class ByteTracker:
    def __init__(self, *, max_missed: int = 15, iou_threshold: float = 0.3) -> None:
        self._tracks: dict[str, _InternalTrack] = {}
        self._next_id = 1
        self._max_missed = max_missed
        self._iou_threshold = iou_threshold

    def update(self, detections: list[Detection]) -> list[Track]:
        matched_ids: set[str] = set()
        updated: dict[str, _InternalTrack] = {}

        for detection in detections:
            best_id: str | None = None
            best_iou = 0.0
            for track_id, track in self._tracks.items():
                if track_id in matched_ids:
                    continue
                iou = _iou(track.bbox, detection.bbox)
                if iou > best_iou:
                    best_iou = iou
                    best_id = track_id

            if best_id is not None and best_iou >= self._iou_threshold:
                updated[best_id] = _InternalTrack(
                    track_id=best_id,
                    bbox=detection.bbox,
                    confidence=detection.confidence,
                    missed=0,
                )
                matched_ids.add(best_id)
            else:
                track_id = f"trk-{self._next_id}"
                self._next_id += 1
                updated[track_id] = _InternalTrack(
                    track_id=track_id,
                    bbox=detection.bbox,
                    confidence=detection.confidence,
                    missed=0,
                )
                matched_ids.add(track_id)

        for track_id, track in self._tracks.items():
            if track_id in matched_ids:
                continue
            missed = track.missed + 1
            if missed <= self._max_missed:
                updated[track_id] = _InternalTrack(
                    track_id=track.track_id,
                    bbox=track.bbox,
                    confidence=track.confidence,
                    missed=missed,
                )

        self._tracks = updated
        return [
            Track(track_id=t.track_id, bbox=t.bbox, confidence=t.confidence)
            for t in self._tracks.values()
            if t.missed == 0
        ]


def _iou(
    a: tuple[int, int, int, int],
    b: tuple[int, int, int, int],
) -> float:
    ax1, ay1, ax2, ay2 = a
    bx1, by1, bx2, by2 = b
    inter_x1 = max(ax1, bx1)
    inter_y1 = max(ay1, by1)
    inter_x2 = min(ax2, bx2)
    inter_y2 = min(ay2, by2)
    if inter_x2 <= inter_x1 or inter_y2 <= inter_y1:
        return 0.0
    inter = (inter_x2 - inter_x1) * (inter_y2 - inter_y1)
    area_a = max(1, (ax2 - ax1) * (ay2 - ay1))
    area_b = max(1, (bx2 - bx1) * (by2 - by1))
    return inter / float(area_a + area_b - inter)
