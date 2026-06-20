"""Draw detections, tripwire, and counters on a preview frame."""

from __future__ import annotations

import logging

import cv2
import numpy as np
from numpy.typing import NDArray

from triosense_edge.config import TripwireConfig
from triosense_edge.pipeline.types import Detection, Track
from triosense_edge.preview.state import PreviewStats

log = logging.getLogger(__name__)


def annotate_frame(
    image: NDArray[np.uint8],
    *,
    detections: list[Detection],
    tracks: list[Track],
    tripwire: TripwireConfig | None,
    stats: PreviewStats,
) -> NDArray[np.uint8]:
    canvas = image.copy()

    if tripwire is not None:
        (x1, y1), (x2, y2) = tripwire.line
        cv2.line(canvas, (x1, y1), (x2, y2), (0, 255, 136), 2)
        cv2.putText(
            canvas,
            f"tripwire ({tripwire.direction})",
            (x1, max(y1 - 8, 16)),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.5,
            (0, 255, 136),
            1,
            cv2.LINE_AA,
        )

    for detection in detections:
        x1, y1, x2, y2 = detection.bbox
        cv2.rectangle(canvas, (x1, y1), (x2, y2), (255, 51, 102), 2)

    for track in tracks:
        x1, y1, x2, y2 = track.bbox
        cv2.rectangle(canvas, (x1, y1), (x2, y2), (51, 153, 255), 2)
        cv2.putText(
            canvas,
            track.track_id,
            (x1, max(y1 - 6, 12)),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.45,
            (51, 153, 255),
            1,
            cv2.LINE_AA,
        )

    overlay = (
        f"persons={stats.person_count}  IN={stats.enter_count}  OUT={stats.exit_count}  "
        f"fps={stats.fps:.1f}  cam={stats.camera_id}  {stats.status}"
    )
    cv2.rectangle(canvas, (0, 0), (canvas.shape[1], 28), (0, 0, 0), -1)
    cv2.putText(
        canvas,
        overlay,
        (8, 20),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.55,
        (255, 255, 255),
        1,
        cv2.LINE_AA,
    )
    log.debug("annotated preview frame persons=%d", stats.person_count)
    return canvas


def encode_jpeg(image: NDArray[np.uint8]) -> bytes:
    ok, encoded = cv2.imencode(".jpg", image, [int(cv2.IMWRITE_JPEG_QUALITY), 80])
    if not ok:
        log.warning("failed to encode preview jpeg")
        return b""
    return encoded.tobytes()
