#!/usr/bin/env python3
"""Benchmark edge inference FPS on Jetson or dev machine."""

from __future__ import annotations

import argparse
import json
import logging
import time
from pathlib import Path

log = logging.getLogger(__name__)


def main() -> None:
    parser = argparse.ArgumentParser(description="Benchmark TrioSense edge FPS")
    parser.add_argument("--config", required=True, help="Location YAML config path")
    parser.add_argument("--duration-sec", type=int, default=60)
    parser.add_argument("--output", type=Path, default=Path("/tmp/triosense-fps.json"))
    args = parser.parse_args()

    logging.basicConfig(level=logging.INFO)
    log.info("benchmark_fps.start config=%s duration=%ss", args.config, args.duration_sec)

    # Placeholder loop — replace with real pipeline hook on Jetson field test.
    frames = 0
    latencies_ms: list[float] = []
    started = time.perf_counter()
    deadline = started + args.duration_sec

    while time.perf_counter() < deadline:
        frame_start = time.perf_counter()
        time.sleep(0.04)  # ~25 FPS mock on dev; real run uses inference loop
        latencies_ms.append((time.perf_counter() - frame_start) * 1000)
        frames += 1

    elapsed = time.perf_counter() - started
    latencies_ms.sort()
    p95_idx = max(0, int(len(latencies_ms) * 0.95) - 1)
    result = {
        "config": args.config,
        "duration_sec": args.duration_sec,
        "frames": frames,
        "mean_fps": round(frames / elapsed, 2),
        "p95_ms": round(latencies_ms[p95_idx], 2) if latencies_ms else 0,
        "dropped_pct": 0.0,
    }

    args.output.write_text(json.dumps(result, indent=2))
    log.info("benchmark_fps.complete mean_fps=%s p95_ms=%s", result["mean_fps"], result["p95_ms"])
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
