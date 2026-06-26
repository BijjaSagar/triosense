#!/usr/bin/env bash
set -euo pipefail
export TRIOSENSE_WORKER_CONTAINER="${TRIOSENSE_QUEUE_CONTAINER:-triosense-queue}"
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/backend-worker-docker.sh" \
  php artisan queue:work redis --sleep=1 --tries=3
