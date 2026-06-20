# MQTT broker failure runbook

## Symptoms
- All edge devices show offline simultaneously
- MQTT subscriber daemon logs connection failures
- No new queue_events rows

## Diagnosis
1. `docker compose ps emqx` (local) or check EMQX HA dashboard (prod)
2. Backend logs: `MqttSubscriberCommand` connection errors
3. Test port: `nc -zv $TRIOSENSE_MQTT_HOST $TRIOSENSE_MQTT_PORT`

## Recovery
1. Restart EMQX: `docker compose restart emqx`
2. Restart backend subscriber: `php artisan mqtt:subscribe` (supervisor-managed in prod)
3. Edges replay buffered SQLite events on reconnect

## Target recovery time
- Single-node dev: <2 minutes
- HA pair failover: <30 seconds
