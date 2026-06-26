#!/usr/bin/env bash
set -euo pipefail
export TRIOSENSE_WORKER_CONTAINER="${TRIOSENSE_TICK_CONTAINER:-triosense-tick}"
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/backend-worker-docker.sh" \
  php artisan triosense:fifo-tick
