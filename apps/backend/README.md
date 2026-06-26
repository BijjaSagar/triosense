# TrioSense Backend

Laravel 11 application that hosts the central FIFO engine, REST API, MQTT subscriber, and Reverb WebSocket server.

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Then in 4 separate terminals (or via Make targets at the repo root):

```bash
php artisan serve                       # REST API on :8000
php artisan reverb:start                # WebSocket on :8080
php artisan queue:work redis            # Background jobs
php artisan triosense:mqtt-subscribe    # MQTT subscriber daemon
```

Or from the repo root:
```bash
make backend-test backend-stan
```

### macOS / host PHP without phpredis

Queue and FIFO tick dispatch use Redis from PHP. On a Mac with PHP 8.5+ and no `ext-redis`, run workers in Docker from the repo root:

```bash
make backend-serve-docker
make backend-queue-docker
make backend-tick-docker
make backend-reverb-docker
```

To build `ext-redis` locally via PECL, answer **no** to optional serializers, or:

```bash
pecl install -D 'enable-redis-igbinary=no' redis
```


## What lives where

```
app/
├── Domain/           Pure domain logic. No I/O. Heavily tested.
│   ├── Fifo/         CutoffCalculator — the heart of the system
│   ├── Queue/        Queue position arithmetic
│   └── Announcement/ Template rendering with placeholders
├── Http/             Controllers, requests, resources, middleware
├── Jobs/             Queue jobs (FifoTickJob, RehydrateLiveStateJob)
├── Models/           Eloquent — every operational model uses BelongsToTenant
├── Mqtt/             MQTT subscriber daemon + per-topic handlers
├── Broadcasting/     Reverb events (LocationStateUpdated, ...)
├── Services/         AuditLogger, QuotaService, ...
└── Console/Commands/ Artisan commands

database/
├── migrations/       Timestamped, append-only
├── seeders/          DatabaseSeeder bootstraps TTD tenant + 3 locations
└── factories/        Model factories for tests
```

## Custom Artisan commands

| Command | Purpose |
| --- | --- |
| `triosense:mqtt-subscribe` | Long-lived daemon, subscribes to all `triosense/loc/+/event/+` topics |
| `triosense:fifo-tick` | Runs the FIFO decision loop at 1Hz per location |
| `triosense:replay {date} {location_id}` | Replays a day's queue_events into Redis (for recovery or analysis) |
| `triosense:edge-provision {device_uid}` | Generates per-device MQTT cert + registration token |

## Testing

```bash
./vendor/bin/pest                       # Full suite
./vendor/bin/pest tests/Unit/Fifo       # Just FIFO domain tests
./vendor/bin/pest --filter cutoff       # By name
./vendor/bin/phpstan analyse            # Static analysis (level 8)
./vendor/bin/pint                       # Format
```

## Read before editing

- [`../../CLAUDE.md`](../../CLAUDE.md) — AI agent conventions
- [`../../ARCHITECTURE.md`](../../ARCHITECTURE.md) — system architecture
- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) — REST + WebSocket + MQTT contracts
- [`../../DATABASE_SCHEMA.md`](../../DATABASE_SCHEMA.md) — full DDL
- [`.cursor/rules/01-backend-laravel.mdc`](../../.cursor/rules/01-backend-laravel.mdc) — backend conventions
