"""Production edge entrypoint: vision pipeline + optional backend config fetch."""

from __future__ import annotations

import argparse
import asyncio
import contextlib
import logging
import os
import signal
import sys
from pathlib import Path

from triosense_edge.config_loader import export_model, fetch_edge_config, load_config
from triosense_edge.pipeline.runner import PipelineRunner
from triosense_edge.transport.buffer import EventBuffer
from triosense_edge.transport.mqtt_client import MqttClient

log = logging.getLogger(__name__)


async def _run_pipeline(config_path: Path | None, fetch_from_api: bool) -> int:
    if fetch_from_api:
        device_uid = os.environ["TRIOSENSE_EDGE_DEVICE_UID"]
        api_key = os.environ["TRIOSENSE_EDGE_API_KEY"]
        base_url = os.environ.get("TRIOSENSE_API_BASE_URL", "http://localhost:8000")
        config = await fetch_edge_config(device_uid, api_key, base_url)
    else:
        config = load_config(config_path)

    buffer = EventBuffer(config.buffer_db_path)
    await buffer.initialize()
    mqtt = MqttClient(config.mqtt, buffer)
    await mqtt.connect()

    runner = PipelineRunner(config=config, mqtt=mqtt)
    await runner.start()

    stop = asyncio.Event()
    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        with contextlib.suppress(NotImplementedError):
            loop.add_signal_handler(sig, stop.set)

    log.info("edge pipeline running device=%s location=%d", config.device_uid, config.location_id)
    try:
        await stop.wait()
    finally:
        await runner.stop()
        await mqtt.disconnect()
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="triosense-edge")
    parser.add_argument("--config", type=Path, default=None, help="YAML config path")
    parser.add_argument("--fetch-config", action="store_true", help="Load cameras from backend API")
    parser.add_argument(
        "--export-model",
        action="store_true",
        help="Export YOLOv8n to TensorRT engine",
    )
    parser.add_argument("--log-level", default=os.environ.get("TRIOSENSE_LOG_LEVEL", "INFO"))
    args = parser.parse_args(argv)

    logging.basicConfig(
        level=getattr(logging, str(args.log_level).upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s %(message)s",
    )

    if args.export_model:
        config = load_config(args.config)
        export_model(config, Path("models"))
        return 0

    return asyncio.run(_run_pipeline(args.config, args.fetch_config))


if __name__ == "__main__":
    sys.exit(main())
