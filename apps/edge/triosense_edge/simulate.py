"""
Synthetic event publisher for development.

Publishes ENTER, EXIT, and ISSUE events to the local MQTT broker as if a
real edge device were detecting them. Use this to drive the backend FIFO
loop without any hardware.

Usage:
    poetry run triosense-edge-simulate --location-id=1
    poetry run triosense-edge-simulate --location-id=3 --scenario=festival_morning

Scenarios:
    normal           ~20 arrivals/min, ~18 issuances/min — typical Bhudevi weekday
    festival_morning ~40 arrivals/min, ~22 issuances/min — Brahmotsavam pace
    quiet            ~5 arrivals/min, ~12 issuances/min — late morning
    sprint           bursts of 80 arrivals/min for 10s every 90s — peak crush
"""

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import os
import random
import signal
import sys
import uuid
from dataclasses import dataclass
from datetime import datetime, timezone

import paho.mqtt.client as mqtt

log = logging.getLogger("triosense.simulate")

DEFAULT_BROKER_HOST = os.environ.get("TRIOSENSE_MQTT_HOST", "127.0.0.1")
DEFAULT_BROKER_PORT = int(os.environ.get("TRIOSENSE_MQTT_PORT", "1883"))
DEFAULT_TOPIC_PREFIX = os.environ.get("TRIOSENSE_MQTT_TOPIC_PREFIX", "triosense")


@dataclass(frozen=True)
class Scenario:
    name: str
    arrival_rate_per_min: float
    issuance_rate_per_min: float
    burst_rate_per_min: float = 0.0
    burst_duration_s: float = 0.0
    burst_period_s: float = 0.0


SCENARIOS: dict[str, Scenario] = {
    "normal": Scenario("normal", 20.0, 18.0),
    "festival_morning": Scenario("festival_morning", 40.0, 22.0),
    "quiet": Scenario("quiet", 5.0, 12.0),
    "sprint": Scenario("sprint", 20.0, 18.0,
                       burst_rate_per_min=80.0,
                       burst_duration_s=10.0,
                       burst_period_s=90.0),
}


def _now_iso() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="milliseconds").replace("+00:00", "Z")


def _build_event_payload(device_uid: str, camera_id: int) -> dict[str, object]:
    return {
        "v": 1,
        "device_uid": device_uid,
        "camera_id": camera_id,
        "occurred_at": _now_iso(),
        "track_id": f"trk-{random.randint(1000, 99999)}",
        "confidence": round(random.uniform(0.85, 0.98), 3),
        "metadata": {
            "frame_number": random.randint(1_000_000, 9_999_999),
            "bbox": [random.randint(100, 1500),
                     random.randint(100, 800),
                     random.randint(1600, 1900),
                     random.randint(900, 1080)],
        },
    }


def _build_heartbeat_payload(device_uid: str, uptime_s: int) -> dict[str, object]:
    return {
        "v": 1,
        "device_uid": device_uid,
        "timestamp": _now_iso(),
        "uptime_seconds": uptime_s,
        "cpu_percent": round(random.uniform(25.0, 60.0), 1),
        "mem_percent": round(random.uniform(40.0, 75.0), 1),
        "temp_celsius": round(random.uniform(55.0, 72.0), 1),
        "cameras": [
            {"camera_id": 17, "status": "ok",
             "fps": round(random.uniform(14.0, 15.2), 1),
             "last_frame_at": _now_iso()},
            {"camera_id": 18, "status": "ok",
             "fps": round(random.uniform(14.0, 15.2), 1),
             "last_frame_at": _now_iso()},
        ],
        "buffer_size": 0,
    }


class Simulator:
    """Co-routine that publishes one stream of events according to a scenario."""

    def __init__(
        self,
        client: mqtt.Client,
        location_id: int,
        device_uid: str,
        scenario: Scenario,
        topic_prefix: str = DEFAULT_TOPIC_PREFIX,
    ) -> None:
        self._client = client
        self._location_id = location_id
        self._device_uid = device_uid
        self._scenario = scenario
        self._prefix = topic_prefix
        self._stop = asyncio.Event()
        self._started_at = datetime.now(timezone.utc)

    def stop(self) -> None:
        self._stop.set()

    async def run(self) -> None:
        await asyncio.gather(
            self._loop_event("enter", camera_id=17,
                             base_rate=self._scenario.arrival_rate_per_min),
            self._loop_event("issue", camera_id=18,
                             base_rate=self._scenario.issuance_rate_per_min),
            self._loop_heartbeat(),
            self._loop_burst(),
            return_exceptions=False,
        )

    async def _loop_event(self, event_type: str, camera_id: int, base_rate: float) -> None:
        topic = f"{self._prefix}/loc/{self._location_id}/event/{event_type}"
        while not self._stop.is_set():
            # Poisson-ish: exponential interarrival around the base rate
            interval_s = max(0.05, random.expovariate(base_rate / 60.0))
            try:
                await asyncio.wait_for(self._stop.wait(), timeout=interval_s)
                return
            except asyncio.TimeoutError:
                pass
            payload = _build_event_payload(self._device_uid, camera_id)
            self._publish(topic, payload, qos=1)
            log.info("%s loc=%d device=%s", event_type.upper(),
                     self._location_id, self._device_uid)

    async def _loop_heartbeat(self) -> None:
        topic = f"{self._prefix}/loc/{self._location_id}/edge/{self._device_uid}/heartbeat"
        while not self._stop.is_set():
            try:
                await asyncio.wait_for(self._stop.wait(), timeout=5.0)
                return
            except asyncio.TimeoutError:
                pass
            uptime_s = int((datetime.now(timezone.utc) - self._started_at).total_seconds())
            payload = _build_heartbeat_payload(self._device_uid, uptime_s)
            self._publish(topic, payload, qos=0)

    async def _loop_burst(self) -> None:
        s = self._scenario
        if s.burst_rate_per_min <= 0 or s.burst_duration_s <= 0 or s.burst_period_s <= 0:
            return
        topic = f"{self._prefix}/loc/{self._location_id}/event/enter"
        while not self._stop.is_set():
            try:
                await asyncio.wait_for(self._stop.wait(), timeout=s.burst_period_s)
                return
            except asyncio.TimeoutError:
                pass
            log.warning("BURST loc=%d starting (%.0f/min for %.0fs)",
                        self._location_id, s.burst_rate_per_min, s.burst_duration_s)
            burst_end = asyncio.get_event_loop().time() + s.burst_duration_s
            while not self._stop.is_set() and asyncio.get_event_loop().time() < burst_end:
                self._publish(topic, _build_event_payload(self._device_uid, 17), qos=1)
                await asyncio.sleep(60.0 / s.burst_rate_per_min)

    def _publish(self, topic: str, payload: dict[str, object], qos: int) -> None:
        try:
            info = self._client.publish(topic, json.dumps(payload), qos=qos)
            if info.rc != mqtt.MQTT_ERR_SUCCESS:
                log.warning("publish failed rc=%s topic=%s", info.rc, topic)
        except Exception as exc:  # noqa: BLE001
            log.error("publish exception topic=%s err=%s", topic, exc)


async def _async_main(args: argparse.Namespace) -> int:
    scenario = SCENARIOS[args.scenario]
    device_uid = args.device_uid or f"edge-sim-{args.location_id:02d}"

    client = mqtt.Client(
        callback_api_version=mqtt.CallbackAPIVersion.VERSION2,  # type: ignore[attr-defined]
        client_id=f"triosense-simulate-{uuid.uuid4().hex[:8]}",
        transport="tcp",
    )
    client.on_connect = lambda c, u, f, rc, p: log.info("MQTT connected rc=%s", rc)
    client.on_disconnect = lambda *a: log.warning("MQTT disconnected")
    client.connect(args.broker_host, args.broker_port, keepalive=30)
    client.loop_start()

    sim = Simulator(
        client=client,
        location_id=args.location_id,
        device_uid=device_uid,
        scenario=scenario,
        topic_prefix=args.topic_prefix,
    )

    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            loop.add_signal_handler(sig, sim.stop)
        except NotImplementedError:
            # Windows doesn't support add_signal_handler for SIGTERM
            pass

    log.info(
        "Simulating loc=%d device=%s scenario=%s (arrivals=%.0f/min issuances=%.0f/min)",
        args.location_id, device_uid, scenario.name,
        scenario.arrival_rate_per_min, scenario.issuance_rate_per_min,
    )

    try:
        await sim.run()
    finally:
        client.loop_stop()
        client.disconnect()
        log.info("Simulator stopped cleanly")
    return 0


def _parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        prog="triosense-edge-simulate",
        description="Publish synthetic queue events to MQTT for backend testing.",
    )
    parser.add_argument("--location-id", type=int, required=True,
                        help="Target location_id (1, 2, or 3 for TTD)")
    parser.add_argument("--device-uid", type=str, default=None,
                        help="Override device UID (defaults to edge-sim-{loc})")
    parser.add_argument("--scenario", type=str, default="normal",
                        choices=sorted(SCENARIOS.keys()),
                        help="Traffic pattern to simulate")
    parser.add_argument("--broker-host", type=str, default=DEFAULT_BROKER_HOST)
    parser.add_argument("--broker-port", type=int, default=DEFAULT_BROKER_PORT)
    parser.add_argument("--topic-prefix", type=str, default=DEFAULT_TOPIC_PREFIX)
    parser.add_argument("--log-level", type=str, default="INFO",
                        choices=["DEBUG", "INFO", "WARNING", "ERROR"])
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = _parse_args(argv)
    logging.basicConfig(
        level=getattr(logging, args.log_level),
        format="%(asctime)s %(levelname)s %(name)s %(message)s",
    )
    return asyncio.run(_async_main(args))


if __name__ == "__main__":
    sys.exit(main())
