# Shadow mode — 7-day field pilot

> **Purpose.** Run TrioSense in `shadow` mode at one counter for seven operating days before live cutover. Predictions are computed and logged but do not drive PA announcements or token denial.
> **Owner.** TTD operations lead + Triounity field engineer.

---

## Preconditions

- [ ] Location `mode=shadow` in database (default for seeded locations)
- [ ] Edge device publishing enter/exit/issue events reliably
- [ ] Backend MQTT subscriber + FIFO tick worker running
- [ ] Dashboard accessible to ops team
- [ ] Daily quota set in `daily_quotas` for pilot location

---

## Daily checklist (repeat × 7 days)

### Morning (before counter opens)

| # | Task | How |
|---|------|-----|
| 1 | Verify edge heartbeat | Dashboard → location → edge device status green |
| 2 | Confirm shadow banner | Location detail shows "Shadow mode" |
| 3 | Check Redis cold start | `make health`; if Redis restarted overnight run `make replay DATE=<today> LOCATION=<id>` |
| 4 | Baseline API snapshot | `GET /api/v1/locations/{id}/state` — record quota, issued=0 |

### During operations (every 2 hours)

| # | Task | How |
|---|------|-----|
| 5 | Compare live queue vs camera | Spot-check queue_tail vs visual line length |
| 6 | Note predicted cutoff | Record `cutoff_position` and `status` from state API |
| 7 | Capture anomalies | Edge offline, MQTT gaps, manual overrides — log in pilot spreadsheet |

### End of day (after counter closes)

| # | Task | How |
|---|------|-----|
| 8 | Record actual closure position | Last issued token number from counter register |
| 9 | Pull cutoff accuracy | `GET /api/v1/locations/{id}/cutoff-accuracy?from=<day>&to=<day>` |
| 10 | Export metrics row | See **Metrics to collect** below |
| 11 | Review dashboard shadow tab | Recharts daily delta chart |

---

## Metrics to collect

Record one row per day in `docs/runbooks/shadow-mode-pilot-log.csv` (create on site):

| Column | Source |
|--------|--------|
| `date` | Operating day (IST) |
| `location_id` | Pilot counter |
| `predicted_cutoff_position` | Last shadow `cutoff_declared` event or API |
| `actual_closure_position` | Manual count from counter staff |
| `delta_positions` | `abs(predicted - actual)` |
| `within_tolerance` | delta ≤ 10 |
| `max_queue_length` | Max `queue_tail - queue_head` from state snapshots |
| `mqtt_events_count` | `SELECT COUNT(*) FROM queue_events WHERE location_id=? AND DATE(occurred_at)=?` |
| `edge_uptime_pct` | Heartbeats received / expected |
| `notes` | Free text |

### API usage — cutoff accuracy

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://staging-api.triosense.in/api/v1/locations/3/cutoff-accuracy?from=2026-06-14&to=2026-06-20" \
  | jq '.data.summary'
```

Expected summary keys: `days_with_predictions`, `days_within_tolerance`, `median_delta`, `max_delta`.

---

## Success criteria (go / no-go for live cutover)

| Criterion | Target |
|-----------|--------|
| Days with valid predictions | ≥ 5 of 7 |
| **Median delta** | **≤ 10 positions** |
| Max delta | ≤ 25 positions |
| Edge uptime | ≥ 99% during operating hours |
| MQTT event loss | 0 unexplained gaps > 5 minutes |
| False cutoff alarms (APPROACHING → OPEN without issue) | ≤ 2 per day |

If median delta > 10 after day 5, pause pilot, review ADR-0004 safety margin tuning, and extend shadow period.

---

## Escalation

| Issue | Runbook |
|-------|---------|
| Edge offline | [`edge-offline.md`](./edge-offline.md) |
| MQTT broker down | [`mqtt-broker-down.md`](./mqtt-broker-down.md) |
| Redis cold start | [`redis-cold-start.md`](./redis-cold-start.md) |
| Incident | [`incident-response.md`](./incident-response.md) |

---

## Post-pilot

1. Operations sign-off on median delta ≤ 10
2. Change location `mode` to `live` via dashboard settings or `PATCH /locations/{id}`
3. Enable PA integration (`TRIOSENSE_PA_CONTROLLER_URL`)
4. File ADR if safety margin or forecast constants were tuned during pilot
