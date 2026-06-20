"""Shared pipeline value types."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import UTC, datetime

import numpy as np
from numpy.typing import NDArray


@dataclass(frozen=True)
class Frame:
    image: NDArray[np.uint8]
    timestamp: datetime
    frame_number: int


@dataclass(frozen=True)
class Detection:
    bbox: tuple[int, int, int, int]
    confidence: float
    class_id: int = 0


@dataclass
class Track:
    track_id: str
    bbox: tuple[int, int, int, int]
    confidence: float
    centroid: tuple[float, float] = field(init=False)

    def __post_init__(self) -> None:
        x1, y1, x2, y2 = self.bbox
        object.__setattr__(self, "centroid", ((x1 + x2) / 2.0, (y1 + y2) / 2.0))


def utc_now() -> datetime:
    return datetime.now(UTC)
