"""Subscribe to backend MQTT command topics and act on close_entry."""

from __future__ import annotations

import json
import logging
from typing import Any

import paho.mqtt.client as mqtt

from triosense_edge.config import MqttConfig

log = logging.getLogger(__name__)


class CommandSubscriber:
    """Handles triosense/loc/{id}/command/{device_id} messages."""

    def __init__(self, config: MqttConfig, location_id: int, device_uid: str) -> None:
        self._config = config
        self._location_id = location_id
        self._device_uid = device_uid
        self._entry_closed = False

    @property
    def entry_closed(self) -> bool:
        return self._entry_closed

    def topic(self) -> str:
        prefix = self._config.topic_prefix
        return f"{prefix}/loc/{self._location_id}/command/{self._device_uid}"

    def attach(self, client: mqtt.Client) -> None:
        topic = self.topic()
        client.subscribe(topic, qos=1)
        log.info("command subscriber listening on %s", topic)

        def _on_message(
            _client: mqtt.Client,
            _userdata: object,
            message: mqtt.MQTTMessage,
        ) -> None:
            try:
                payload: dict[str, Any] = json.loads(message.payload.decode("utf-8"))
                self._handle(payload)
            except (json.JSONDecodeError, UnicodeDecodeError) as exc:
                log.error("invalid command payload: %s", exc)

        client.message_callback_add(topic, _on_message)

    def _handle(self, payload: dict[str, Any]) -> None:
        action = payload.get("action")
        log.info("command received action=%s payload=%s", action, payload)

        if action == "close_entry":
            cutoff = payload.get("cutoff_position")
            self._entry_closed = True
            log.warning(
                "ENTRY TRIPWIRE CLOSE SIGNAL — cutoff_position=%s "
                "(operator alert: red overhead light)",
                cutoff,
            )
        elif action == "play_announcement":
            log.info("play_announcement command received: %s", payload.get("audio_file_path"))
        else:
            log.warning("unknown command action: %s", action)
