# Staging — FCM push + Sentry observability

> **Purpose.** Configure Firebase Cloud Messaging and Sentry on the staging environment before production cutover.

---

## FCM (Firebase Cloud Messaging)

TrioSense supports **legacy server key** or **HTTP v1** (recommended).

### Option A — Legacy API (quick staging)

1. Firebase Console → Project Settings → Cloud Messaging → **Server key**
2. Set on staging backend:

```bash
TRIOSENSE_FCM_SERVER_KEY=AAAA...
```

3. Mobile app registers token via `POST /api/v1/users/me/fcm-token`
4. Trigger test push by declaring cutoff in shadow mode or run:

```bash
php artisan tinker
>>> app(\App\Services\Notifications\FcmService::class)->send('<device-token>', 'Test', 'Staging push OK');
```

### Option B — HTTP v1 (production path)

1. Firebase Console → Service accounts → Generate new private key (JSON)
2. Copy JSON to staging host (not in git): `/etc/triosense/fcm-service-account.json`
3. Set env:

```bash
TRIOSENSE_FCM_PROJECT_ID=triosense-staging
TRIOSENSE_FCM_CREDENTIALS_PATH=/etc/triosense/fcm-service-account.json
```

4. Leave `TRIOSENSE_FCM_SERVER_KEY` empty to prefer v1

Implementation: `App\Services\Notifications\FcmService` (legacy + v1 JWT flow).

---

## Sentry (error tracking)

### Install on staging host

```bash
cd apps/backend
composer require sentry/sentry-laravel
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

### Environment

```bash
SENTRY_LARAVEL_DSN=https://<key>@o<org>.ingest.sentry.io/<project>
SENTRY_TRACES_SAMPLE_RATE=0.1
APP_ENV=staging
```

`App\Providers\SentryServiceProvider` registers Sentry only when `SENTRY_LARAVEL_DSN` is set. Local dev without DSN is a no-op stub (debug log only).

### Verify

1. Trigger test exception in tinker or hit a known 500 route in staging
2. Confirm event in Sentry dashboard within 30s
3. Check release tag matches deploy git SHA

---

## Staging checklist

| Item | Done |
|------|------|
| FCM credentials configured (v1 or legacy) | ☐ |
| Test push received on supervisor Flutter app | ☐ |
| Cutoff override push notification fires | ☐ |
| Sentry DSN set | ☐ |
| `sentry/sentry-laravel` installed on staging | ☐ |
| Test error appears in Sentry | ☐ |
| `.env.example` documents all vars | ☐ |

---

## Security notes

- Never commit FCM service account JSON or server keys
- Restrict FCM JSON file permissions to `www-data` / `640`
- Use separate Firebase projects for staging vs production
- Set Sentry `traces_sample_rate` ≤ 0.2 on staging to control quota
