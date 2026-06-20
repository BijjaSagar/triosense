#!/usr/bin/env bash
# Simulate elevated MQTT event rate for load testing (Sprint 10).
# Usage: ./infra/scripts/load-test-events.sh [location_id] [scenario]
# Scenarios: normal, burst (see triosense_edge.simulate SCENARIOS)
set -euo pipefail

LOCATION_ID="${1:-3}"
SCENARIO="${2:-burst}"

echo "Running burst scenario on location ${LOCATION_ID} (Ctrl+C to stop after ~2 min)"

cd "$(dirname "$0")/../../apps/edge"
timeout 120 poetry run python -m triosense_edge.simulate \
  --location-id="${LOCATION_ID}" \
  --scenario="${SCENARIO}" \
  --log-level=INFO || true

echo "Load test window complete. Check backend logs for FifoTickService lag."
