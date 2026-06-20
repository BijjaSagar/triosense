"""Tests for MQTT command subscriber."""

from __future__ import annotations

import json

from triosense_edge.config import MqttConfig
from triosense_edge.transport.command_subscriber import CommandSubscriber


def test_command_subscriber_topic() -> None:
    config = MqttConfig(
        broker_host="localhost",
        broker_port=1883,
        client_id="edge-test",
        topic_prefix="triosense",
        tls_enabled=False,
        tls_ca_path=None,
        tls_cert_path=None,
        tls_key_path=None,
    )
    sub = CommandSubscriber(config, location_id=3, device_uid="edge-sim-03")
    assert sub.topic() == "triosense/loc/3/command/edge-sim-03"


def test_close_entry_sets_flag() -> None:
    config = MqttConfig(
        broker_host="localhost",
        broker_port=1883,
        client_id="edge-test",
        topic_prefix="triosense",
        tls_enabled=False,
        tls_ca_path=None,
        tls_cert_path=None,
        tls_key_path=None,
    )
    sub = CommandSubscriber(config, location_id=3, device_uid="edge-sim-03")
    assert sub.entry_closed is False
    sub._handle({"action": "close_entry", "cutoff_position": 5000, "v": 1})
    assert sub.entry_closed is True
