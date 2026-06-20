# MySQL failure runbook

## Symptoms
- MQTT subscriber pauses (by design — no silent loss)
- API returns 503 on state/history endpoints
- Migrations or seed fail

## Diagnosis
1. `mysqladmin ping -h $DB_HOST`
2. Check disk space on DB volume
3. Laravel logs: PDO connection exceptions

## Recovery
1. Restore MySQL service or failover to replica
2. Restart backend once MySQL is healthy
3. Edge devices continue buffering; subscriber drains backlog

## Target recovery time
- Service restart: <5 minutes
- Failover to replica: per NIC SLA (<15 minutes)

## Data integrity
- No events lost if edges buffered during outage
- FIFO decisions during outage are not persisted — replay from queue_events after recovery
