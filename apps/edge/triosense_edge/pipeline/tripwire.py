"""Tripwire line-crossing detection for IN/OUT events."""

from __future__ import annotations

import logging
from dataclasses import dataclass
from datetime import datetime
from typing import Literal

from triosense_edge.config import TripwireConfig
from triosense_edge.pipeline.types import Track

log = logging.getLogger(__name__)

CrossingDirection = Literal["in", "out"]


@dataclass(frozen=True)
class TripwireEvent:
    track_id: str
    direction: CrossingDirection
    confidence: float
    bbox: tuple[int, int, int, int]
    occurred_at: datetime


class TripwireDetector:
    def __init__(self, config: TripwireConfig) -> None:
        self._line = config.line
        self._configured_direction = config.direction
        self._last_centroid: dict[str, tuple[float, float]] = {}
        self._emitted: set[str] = set()

    def process(self, tracks: list[Track], occurred_at: datetime) -> list[TripwireEvent]:
        events: list[TripwireEvent] = []
        for track in tracks:
            previous = self._last_centroid.get(track.track_id)
            current = track.centroid
            self._last_centroid[track.track_id] = current
            if previous is None:
                continue

            if not _crosses_tripwire(previous, current, self._line[0], self._line[1]):
                continue

            crossing = _crossing_direction(previous, current, self._line[0], self._line[1])
            if crossing is None:
                continue

            event_direction: CrossingDirection
            if crossing == self._configured_direction:
                event_direction = "in"
            elif _opposite_direction(crossing) == self._configured_direction:
                event_direction = "out"
            else:
                continue

            dedupe_key = f"{track.track_id}:{event_direction}"
            if dedupe_key in self._emitted:
                continue
            self._emitted.add(dedupe_key)

            events.append(
                TripwireEvent(
                    track_id=track.track_id,
                    direction=event_direction,
                    confidence=track.confidence,
                    bbox=track.bbox,
                    occurred_at=occurred_at,
                )
            )
            log.info(
                "tripwire crossing track=%s direction=%s",
                track.track_id,
                event_direction,
            )
        return events

    def reset(self) -> None:
        self._last_centroid.clear()
        self._emitted.clear()


def _crosses_tripwire(
    previous: tuple[float, float],
    current: tuple[float, float],
    line_start: tuple[int, int],
    line_end: tuple[int, int],
) -> bool:
    prev_side = _line_side(previous, line_start, line_end)
    curr_side = _line_side(current, line_start, line_end)
    if prev_side == 0 or curr_side == 0:
        return True
    return (prev_side < 0) != (curr_side < 0)


def _line_side(
    point: tuple[float, float],
    line_start: tuple[int, int],
    line_end: tuple[int, int],
) -> float:
    ax, ay = line_start
    bx, by = line_end
    px, py = point
    return (bx - ax) * (py - ay) - (by - ay) * (px - ax)


def _segments_intersect(
    p1: tuple[float, float],
    p2: tuple[float, float],
    q1: tuple[int, int],
    q2: tuple[int, int],
) -> bool:
    return _crosses_tripwire(p1, p2, q1, q2)


def _crossing_direction(
    previous: tuple[float, float],
    current: tuple[float, float],
    line_start: tuple[int, int],
    line_end: tuple[int, int],
) -> Literal["up", "down", "left", "right"] | None:
    dx = line_end[0] - line_start[0]
    dy = line_end[1] - line_start[1]
    move_x = current[0] - previous[0]
    move_y = current[1] - previous[1]
    cross = dx * move_y - dy * move_x
    if abs(cross) < 1e-6:
        return None

    if abs(dy) <= abs(dx):
        return "down" if cross > 0 else "up"
    return "right" if cross > 0 else "left"


def _opposite_direction(
    direction: Literal["up", "down", "left", "right"],
) -> Literal["up", "down", "left", "right"]:
    mapping: dict[str, Literal["up", "down", "left", "right"]] = {
        "up": "down",
        "down": "up",
        "left": "right",
        "right": "left",
    }
    return mapping[direction]
