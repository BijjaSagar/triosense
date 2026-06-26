#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE="${TRIOSENSE_PHP_IMAGE:-triosense-php:8.4-local}"
CONTAINER_NAME="${TRIOSENSE_WORKER_CONTAINER:?TRIOSENSE_WORKER_CONTAINER required}"
NETWORK="${TRIOSENSE_DOCKER_NETWORK:-triosense}"
PORT_MAP="${TRIOSENSE_WORKER_PORT_MAP:-}"

docker rm -f "${CONTAINER_NAME}" 2>/dev/null || true

if [[ -n "${PORT_MAP}" ]]; then
  docker run -d \
    --name "${CONTAINER_NAME}" \
    --network "${NETWORK}" \
    -p "${PORT_MAP}" \
    --env-file "${ROOT_DIR}/infra/docker/backend-serve.env" \
    -v "${ROOT_DIR}/apps/backend:/app" \
    -w /app \
    "${IMAGE}" \
    "$@"
else
  docker run -d \
    --name "${CONTAINER_NAME}" \
    --network "${NETWORK}" \
    --env-file "${ROOT_DIR}/infra/docker/backend-serve.env" \
    -v "${ROOT_DIR}/apps/backend:/app" \
    -w /app \
    "${IMAGE}" \
    "$@"
fi

echo "✓ ${CONTAINER_NAME} started: $*"
echo "  logs: docker logs -f ${CONTAINER_NAME}"
