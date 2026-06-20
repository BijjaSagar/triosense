"""Unit tests for tripwire crossing logic."""

from __future__ import annotations

from datetime import UTC, datetime

from triosense_edge.config import TripwireConfig
from triosense_edge.pipeline.tracker import ByteTracker
from triosense_edge.pipeline.tripwire import TripwireDetector
from triosense_edge.pipeline.types import Detection, Track


def test_tripwire_detects_in_crossing() -> None:
    tripwire = TripwireDetector(
        TripwireConfig(line=((100, 500), (900, 500)), direction="down"),
    )
    tripwire._last_centroid["trk-1"] = (440.0, 450.0)  # noqa: SLF001

    moved = Track(track_id="trk-1", bbox=(400, 520, 480, 640), confidence=0.9)
    events = tripwire.process([moved], datetime.now(UTC))

    assert len(events) == 1
    assert events[0].direction == "in"


def test_tripwire_detects_out_crossing() -> None:
    tripwire = TripwireDetector(
        TripwireConfig(line=((100, 500), (900, 500)), direction="down"),
    )
    tripwire._last_centroid["trk-2"] = (440.0, 550.0)  # noqa: SLF001
    moved = Track(track_id="trk-2", bbox=(400, 400, 480, 480), confidence=0.88)
    events = tripwire.process([moved], datetime.now(UTC))

    assert len(events) == 1
    assert events[0].direction == "out"


def test_tracker_assigns_stable_ids() -> None:
    tracker = ByteTracker()
    det = Detection(bbox=(10, 10, 50, 90), confidence=0.9)
    first = tracker.update([det])
    second = tracker.update([Detection(bbox=(12, 12, 52, 92), confidence=0.91)])

    assert len(first) == 1
    assert len(second) == 1
    assert first[0].track_id == second[0].track_id
