"""Fetch edge runtime config from the TrioSense backend API."""

from __future__ import annotations

import logging
import os
from pathlib import Path

import httpx

from triosense_edge.config import CameraConfig, EdgeConfig, MqttConfig, TripwireConfig

log = logging.getLogger(__name__)


def _env_mqtt_config(device_uid: str) -> MqttConfig:
    tls_enabled = os.environ.get("TRIOSENSE_MQTT_TLS", "false").lower() == "true"
    cert = os.environ.get("TRIOSENSE_MQTT_TLS_CERT_PATH")
    key = os.environ.get("TRIOSENSE_MQTT_TLS_KEY_PATH")
    ca = os.environ.get("TRIOSENSE_MQTT_TLS_CA_PATH")
    return MqttConfig(
        broker_host=os.environ.get("TRIOSENSE_MQTT_HOST", "127.0.0.1"),
        broker_port=int(os.environ.get("TRIOSENSE_MQTT_PORT", "1883")),
        client_id=os.environ.get("TRIOSENSE_MQTT_CLIENT_ID", device_uid),
        topic_prefix=os.environ.get("TRIOSENSE_MQTT_TOPIC_PREFIX", "triosense"),
        tls_enabled=tls_enabled,
        tls_cert_path=Path(cert) if cert else None,
        tls_key_path=Path(key) if key else None,
        tls_ca_path=Path(ca) if ca else None,
    )


async def fetch_edge_config(device_uid: str, api_key: str, base_url: str) -> EdgeConfig:
    url = f"{base_url.rstrip('/')}/api/v1/edge/{device_uid}/config"
    headers = {"X-Edge-Api-Key": api_key, "Accept": "application/json"}
    log.info("fetching edge config url=%s device=%s", url, device_uid)
    async with httpx.AsyncClient(timeout=30.0) as client:
        response = await client.get(url, headers=headers)
        response.raise_for_status()
        envelope = response.json()
    data = envelope.get("data", envelope)
    cameras: list[CameraConfig] = []
    for camera in data.get("cameras", []):
        tripwire_raw = camera.get("tripwire")
        tripwire = TripwireConfig.model_validate(tripwire_raw) if tripwire_raw else None
        cameras.append(
            CameraConfig(
                camera_id=int(camera["camera_id"]),
                name=str(camera["name"]),
                role=camera["role"],
                source_type=camera.get("source_type", "rtsp"),
                rtsp_url=str(camera["rtsp_url"]),
                tripwire=tripwire,
            )
        )
    runtime = data.get("runtime", {})
    buffer_path = os.environ.get("TRIOSENSE_BUFFER_DB_PATH", "/var/lib/triosense/buffer.sqlite")
    return EdgeConfig(
        device_uid=str(data["device_uid"]),
        tenant_id=int(data["tenant_id"]),
        location_id=int(data["location_id"]),
        cameras=cameras,
        mqtt=_env_mqtt_config(device_uid),
        heartbeat_seconds=int(runtime.get("heartbeat_seconds", 5)),
        inference_fps=int(runtime.get("inference_fps", 15)),
        inference_confidence_threshold=float(runtime.get("inference_confidence_threshold", 0.5)),
        inference_backend=runtime.get("inference_backend", "cpu"),
        stream_backend=runtime.get("stream_backend", "opencv"),
        buffer_db_path=Path(buffer_path),
        model_path=str(runtime.get("model_path", "yolov8n.pt")),
        rtsp_reconnect_seconds=float(runtime.get("rtsp_reconnect_seconds", 5.0)),
    )


def load_config(config_path: Path | None) -> EdgeConfig:
    if config_path is not None:
        log.info("loading edge config from %s", config_path)
        return EdgeConfig.from_yaml(config_path)
    env_path = os.environ.get("TRIOSENSE_EDGE_CONFIG_PATH")
    if env_path:
        return EdgeConfig.from_yaml(Path(env_path))
    msg = "config path required via --config or TRIOSENSE_EDGE_CONFIG_PATH"
    raise ValueError(msg)


def export_model(config: EdgeConfig, output_dir: Path) -> Path:
    from ultralytics import YOLO

    output_dir.mkdir(parents=True, exist_ok=True)
    model = YOLO(config.model_path)
    export_path = model.export(format="engine", imgsz=1080, device=0)
    log.info("tensorrt engine exported path=%s", export_path)
    return Path(str(export_path))
