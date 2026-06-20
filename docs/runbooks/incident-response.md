# Runbook — Incident Response

> When you are paged by TrioSense, work top-to-bottom through this document. The goal in the first 5 minutes is **diagnose then preserve devotee experience**, not "fix the bug." A wrong cutoff is worse than no cutoff.

---

## Severity ladder

| Sev | Definition | Page on-call? | Notify TTD EO? |
| --- | --- | --- | --- |
| **SEV-1** | Wrong cutoff broadcast OR system commanded edge to close while quota still healthy OR all three counters offline | Yes, immediately | Yes, immediately |
| **SEV-2** | One counter offline; or dashboards down; or PA announcements failing; or one edge degraded | Yes, business hours | Within 30 min |
| **SEV-3** | Stale data > 30s on dashboard; or mobile push delayed; or non-critical alert | Next business day | No |

---

## First 5 minutes — universal triage

1. **Open the operations dashboard** at `https://ops.triosense.in/dashboard`.
2. **Check the three location tiles.** Note status and last-update timestamp for each.
3. **Identify which layer is the problem:**
   - All three locations show stale data → likely backend or broker
   - One location shows stale → likely that location's edge or network
   - Dashboard fully blank → likely Reverb or auth
   - Cutoff value looks wrong but everything green → FIFO logic bug (rare; preserve evidence)
4. **If any location is incorrectly closed or incorrectly open**, execute the override (next section) before anything else.

## Disabling FIFO at one location (preserve devotee experience)

If the system is misbehaving and you suspect it is broadcasting incorrectly:

```
1. Log into dashboard with location_supervisor role.
2. Open the affected location.
3. Click "Override → Force OPEN" (or "Force CLOSED" if that's what's actually happening on the ground).
4. Reason: "Override during investigation"
5. The override is audit-logged and announced via PA.
6. Notify TTD on-site staff that the LED signage may show out-of-date info.
```

This stops the bleeding. You can now investigate without time pressure.

---

## Backend down

### Symptoms
- All three locations stale
- API health check `/api/health` returns 5xx or no response
- Sentry shows backend errors

### Steps
```bash
# 1. SSH to backend host
ssh ops@api.triosense.in

# 2. Check the application
sudo systemctl status triosense-backend
sudo journalctl -u triosense-backend -n 200 --no-pager

# 3. Common fixes
sudo systemctl restart triosense-backend
sudo systemctl restart triosense-reverb
sudo systemctl restart triosense-mqtt-subscriber
sudo systemctl restart triosense-fifo-tick

# 4. Verify
curl https://api.triosense.in/api/health
```

If the backend has been hard down for more than 5 minutes, Redis state is stale. Trigger rehydration:

```bash
php artisan triosense:rehydrate --all
```

---

## MQTT broker down

### Symptoms
- All three edges show "offline" on the dashboard simultaneously
- EMQX dashboard at `https://mqtt.triosense.in:18083` unreachable

### Steps
```bash
# Check EMQX
ssh ops@mqtt.triosense.in
sudo systemctl status emqx
sudo emqx ctl status

# Restart
sudo systemctl restart emqx

# Verify edges reconnect within 30 seconds (check dashboard)
```

**Important:** Edge devices buffer events locally and will replay them once the broker is back. Do not panic about temporary backlog — the FIFO loop catches up automatically within minutes. Do panic if the backlog is still growing after 5 minutes.

---

## One edge device offline

### Symptoms
- One location shows red on dashboard
- Last heartbeat timestamp > 60 seconds old

### Steps
1. **Check from dashboard:** Edge Devices panel shows `last_heartbeat_at`, `ip_address`. Network issue or device issue?
2. **From a network you can reach the site:** ping the edge device IP. Reachable?
3. **Reachable but offline:** SSH into the Jetson:
   ```bash
   ssh edge-bdv-01.triosense.local
   sudo systemctl status triosense-edge
   sudo journalctl -u triosense-edge -n 200 --no-pager
   sudo systemctl restart triosense-edge
   ```
4. **Not reachable:** This is a site infrastructure issue. Call the on-site TTD electronics technician (contact in `docs/runbooks/contacts.md`). Until restored, that counter operates manually.

---

## Cutoff broadcast looks wrong

This is **SEV-1**. Preserve evidence and disable FIFO at that location immediately.

### Steps
1. Override location to "Force OPEN" (or whatever the safe state is) immediately.
2. Take a screenshot of the dashboard state.
3. Note the time precisely.
4. Dump live state:
   ```bash
   php artisan triosense:dump-state --location-id=3 > /tmp/state-loc3-$(date +%s).json
   redis-cli --scan --pattern 'triosense:loc:3:*' | xargs -L1 redis-cli get > /tmp/redis-loc3-$(date +%s).txt
   ```
5. Open a SEV-1 incident in the on-call tracker.
6. Do not redeploy or restart until evidence is captured.

---

## Stale data on dashboard

### Symptoms
- One or more location tiles show "stale" badge (data > 5s old)
- WebSocket reconnect attempts visible in browser DevTools

### Steps
1. Check Reverb:
   ```bash
   sudo systemctl status triosense-reverb
   sudo journalctl -u triosense-reverb -n 100 --no-pager
   ```
2. Restart Reverb if necessary. Clients will reconnect within seconds.
3. If problem persists, check Redis: `redis-cli ping`.

---

## After the incident

1. Write a brief postmortem in `docs/postmortems/YYYY-MM-DD-{slug}.md` within 48 hours.
2. Include: what happened, when, who responded, what we did, what we learned, what we'll change.
3. Open follow-up tickets for any prevention work.

---

## Contacts

See `docs/runbooks/contacts.md` (gitignored — contact list lives in our shared password manager).
