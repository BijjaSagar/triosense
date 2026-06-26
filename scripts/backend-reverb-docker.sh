#!/usr/bin/env bash
set -euo pipefail
REVERB_PORT="${REVERB_PORT:-8080}"
export TRIOSENSE_WORKER_CONTAINER="${TRIOSENSE_REVERB_CONTAINER:-triosense-reverb}"
export TRIOSENSE_WORKER_PORT_MAP="${REVERB_PORT}:8080"
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/backend-worker-docker.sh" \
  php artisan reverb:start --host=0.0.0.0 --port=8080
