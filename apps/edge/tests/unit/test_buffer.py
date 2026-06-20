"""SQLite buffer tests."""

from __future__ import annotations

from pathlib import Path

import pytest

from triosense_edge.transport.buffer import EventBuffer


@pytest.mark.asyncio
async def test_buffer_fifo_drain(tmp_path: Path) -> None:
    db_path = tmp_path / "buffer.sqlite"
    buffer = EventBuffer(db_path)
    await buffer.initialize()

    await buffer.append("triosense/loc/3/event/enter", '{"v":1}')
    await buffer.append("triosense/loc/3/event/enter", '{"v":2}')

    assert await buffer.count() == 2
    rows = await buffer.drain()
    assert rows[0][2] == '{"v":1}'
    assert rows[1][2] == '{"v":2}'

    await buffer.delete_ids([row[0] for row in rows])
    assert await buffer.count() == 0
