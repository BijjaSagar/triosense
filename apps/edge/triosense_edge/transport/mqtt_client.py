"""Async-friendly MQTT client with offline buffering and FIFO replay."""

from __future__ import annotations

import asyncio
import json
import logging
from typing import Any

import paho.mqtt.client as mqtt
from pydantic import BaseModel

from triosense_edge.config import MqttConfig
from triosense_edge.transport.buffer import EventBuffer

log = logging.getLogger(__name__)


class MqttClient:
    def __init__(self, config: MqttConfig, buffer: EventBuffer) -> None:
        self._config = config
        self._buffer = buffer
        self._client = mqtt.Client(
            callback_api_version=mqtt.CallbackAPIVersion.VERSION2,  # type: ignore[attr-defined]
            client_id=config.client_id,
            transport="tcp",
        )
        self._connected = asyncio.Event()
        self._replay_lock = asyncio.Lock()

    async def connect(self) -> None:
        loop = asyncio.get_running_loop()

        def _on_connect(
            client: mqtt.Client,
            userdata: object,
            flags: object,
            reason_code: object,
            properties: object | None,
        ) -> None:
            log.info("mqtt connected rc=%s", reason_code)
            loop.call_soon_threadsafe(self._connected.set)

        def _on_disconnect(
            client: mqtt.Client,
            userdata: object,
            disconnect_flags: object,
            reason_code: object,
            properties: object | None,
        ) -> None:
            log.warning("mqtt disconnected rc=%s", reason_code)
            loop.call_soon_threadsafe(self._connected.clear)

        self._client.on_connect = _on_connect
        self._client.on_disconnect = _on_disconnect

        if self._config.tls_enabled:
            if (
                self._config.tls_ca_path is None
                or self._config.tls_cert_path is None
                or self._config.tls_key_path is None
            ):
                msg = "TLS enabled but cert paths are missing"
                raise ValueError(msg)
            self._client.tls_set(
                ca_certs=str(self._config.tls_ca_path),
                certfile=str(self._config.tls_cert_path),
                keyfile=str(self._config.tls_key_path),
            )

        self._client.connect(self._config.broker_host, self._config.broker_port, keepalive=30)
        self._client.loop_start()
        await self._connected.wait()
        await self.replay_buffer()

    async def disconnect(self) -> None:
        self._client.loop_stop()
        self._client.disconnect()
        self._connected.clear()
        log.info("mqtt client stopped")

    async def publish(self, topic: str, payload: BaseModel | dict[str, Any], qos: int = 1) -> bool:
        if isinstance(payload, BaseModel):
            message = json.dumps(payload.model_dump(mode="json"))
        else:
            message = json.dumps(payload)

        if not self._connected.is_set():
            await self._buffer.append(topic, message)
            return False

        info = self._client.publish(topic, message, qos=qos)
        if info.rc != mqtt.MQTT_ERR_SUCCESS:
            log.warning("publish failed rc=%s topic=%s — buffering", info.rc, topic)
            await self._buffer.append(topic, message)
            return False
        return True

    async def replay_buffer(self) -> int:
        async with self._replay_lock:
            if not self._connected.is_set():
                return 0
            rows = await self._buffer.drain()
            if not rows:
                return 0

            published_ids: list[int] = []
            for row_id, topic, payload in rows:
                info = self._client.publish(topic, payload, qos=1)
                if info.rc != mqtt.MQTT_ERR_SUCCESS:
                    log.error("replay failed at id=%d topic=%s rc=%s", row_id, topic, info.rc)
                    break
                published_ids.append(row_id)

            if published_ids:
                await self._buffer.delete_ids(published_ids)
            log.info("replayed %d/%d buffered events", len(published_ids), len(rows))
            return len(published_ids)

    @property
    def topic_prefix(self) -> str:
        return self._config.topic_prefix

    async def buffered_count(self) -> int:
        return await self._buffer.count()
