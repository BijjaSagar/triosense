# Backend API failure runbook

## Symptoms
- Dashboard login fails or 5xx on all API calls
- FIFO tick logs stop
- WebSocket disconnects

## Diagnosis
1. Health: `curl http://localhost:8000/up`
2. Queue worker: `php artisan queue:work --once`
3. Scheduler: verify `FifoTickCommand` in cron/supervisor

## Recovery
1. Restart PHP-FPM / container: `docker compose restart backend`
2. Clear stuck jobs: `php artisan queue:restart`
3. Cold Redis: `php artisan triosense:rehydrate --location=3` (if command exists) or dispatch `RehydrateLiveStateJob`

## Target recovery time
- Container restart: <3 minutes
- Redis rehydrate: <30 seconds per location
