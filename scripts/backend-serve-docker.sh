#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE="${TRIOSENSE_PHP_IMAGE:-triosense-php:8.4-local}"
CONTAINER_NAME="${TRIOSENSE_SERVE_CONTAINER:-triosense-serve}"
NETWORK="${TRIOSENSE_DOCKER_NETWORK:-triosense}"
PORT="${TRIOSENSE_API_PORT:-8001}"

docker rm -f "${CONTAINER_NAME}" 2>/dev/null || true
docker run -d \
  --name "${CONTAINER_NAME}" \
  --network "${NETWORK}" \
  -p "${PORT}:8001" \
  --env-file "${ROOT_DIR}/infra/docker/backend-serve.env" \
  -v "${ROOT_DIR}/apps/backend:/app" \
  -w /app \
  "${IMAGE}" \
  php -S 0.0.0.0:8001 -t public public/index.php

echo "Backend API: http://localhost:${PORT}"
