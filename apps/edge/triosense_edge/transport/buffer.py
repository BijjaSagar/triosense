"""SQLite-backed FIFO event buffer for MQTT outages."""

from __future__ import annotations

import logging
from pathlib import Path

import aiosqlite

log = logging.getLogger(__name__)


class EventBuffer:
    def __init__(self, db_path: Path) -> None:
        self._db_path = db_path

    async def initialize(self) -> None:
        self._db_path.parent.mkdir(parents=True, exist_ok=True)
        async with aiosqlite.connect(self._db_path) as db:
            await db.execute(
                """
                CREATE TABLE IF NOT EXISTS buffered_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    topic TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    queued_at REAL NOT NULL DEFAULT (julianday('now'))
                )
                """
            )
            await db.commit()
        log.info("event buffer ready path=%s", self._db_path)

    async def append(self, topic: str, payload: str) -> None:
        async with aiosqlite.connect(self._db_path) as db:
            await db.execute(
                "INSERT INTO buffered_events (topic, payload) VALUES (?, ?)",
                (topic, payload),
            )
            await db.commit()
        log.warning("buffered mqtt message topic=%s bytes=%d", topic, len(payload))

    async def count(self) -> int:
        async with aiosqlite.connect(self._db_path) as db:
            cursor = await db.execute("SELECT COUNT(*) FROM buffered_events")
            row = await cursor.fetchone()
        return int(row[0]) if row is not None else 0

    async def drain(self, limit: int = 500) -> list[tuple[int, str, str]]:
        async with aiosqlite.connect(self._db_path) as db:
            cursor = await db.execute(
                "SELECT id, topic, payload FROM buffered_events ORDER BY id LIMIT ?",
                (limit,),
            )
            rows = list(await cursor.fetchall())
        log.info("draining %d buffered events", len(rows))
        return [(int(r[0]), str(r[1]), str(r[2])) for r in rows]

    async def delete_ids(self, ids: list[int]) -> None:
        if not ids:
            return
        placeholders = ",".join("?" for _ in ids)
        async with aiosqlite.connect(self._db_path) as db:
            await db.execute(
                f"DELETE FROM buffered_events WHERE id IN ({placeholders})",
                ids,
            )
            await db.commit()
        log.info("removed %d buffered events after replay", len(ids))
