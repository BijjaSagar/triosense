# Redis failure runbook

## Symptoms
- Live state API returns zeros or stale data
- FIFO tick errors in logs
- Dashboard shows stale banner on all cards

## Diagnosis
1. `redis-cli ping`
2. Check key: `redis-cli GET triosense:loc:3:status`
3. Backend logs: `LocationStateService.cold_redis_rehydrate`

## Recovery
1. Restart Redis: `docker compose restart redis`
2. Trigger rehydrate per location via `RehydrateLiveStateJob::dispatchSync($locationId)`
3. Verify replay from `queue_events` matches expected counters

## Target recovery time
- Rehydrate one location: <30 seconds
- Full three-location rehydrate: <2 minutes
