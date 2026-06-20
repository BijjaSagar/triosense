"""Config model tests."""

from __future__ import annotations

from pathlib import Path

from triosense_edge.config import EdgeConfig


def test_load_example_yaml() -> None:
    path = Path(__file__).resolve().parents[2] / "config" / "location_3.example.yaml"
    config = EdgeConfig.from_yaml(path)
    assert config.location_id == 3
    assert config.cameras[0].tripwire is not None
    assert config.inference_backend == "mock"
