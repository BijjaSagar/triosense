# TrioSense — Database Schema

> **Database engine.** MySQL 8.0, InnoDB, `utf8mb4_unicode_ci`.
> **Migration tool.** Laravel migrations. Run with `php artisan migrate`.
> **Rule.** All operational tables carry `tenant_id` and `location_id`. Never query operational data without both.

---

## 1. Tenant + location hierarchy

### `tenants`
The multi-tenancy root. TTD is tenant 1. Future temple trusts (Shirdi Sai Sansthan, Vaishno Devi, etc.) would be tenants 2+.

```sql
CREATE TABLE tenants (
    tenant_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(120) NOT NULL,
    slug             VARCHAR(60) NOT NULL,
    contact_email    VARCHAR(160) NULL,
    contact_phone    VARCHAR(20) NULL,
    timezone         VARCHAR(60) NOT NULL DEFAULT 'Asia/Kolkata',
    status           ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    deleted_at       TIMESTAMP NULL,
    PRIMARY KEY (tenant_id),
    UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB;
```

### `locations`
One row per physical SSD counter location. For TTD: Vishnu Nivasam, Srinivasam, Bhudevi Complex.

```sql
CREATE TABLE locations (
    location_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    name             VARCHAR(120) NOT NULL,
    short_code       VARCHAR(20) NOT NULL,        -- e.g. 'BDV', 'VSN', 'SRN'
    address          VARCHAR(400) NULL,
    latitude         DECIMAL(10,7) NULL,
    longitude        DECIMAL(10,7) NULL,
    opens_at         TIME NOT NULL DEFAULT '05:00:00',
    closes_at        TIME NOT NULL DEFAULT '12:00:00',
    default_quota    INT UNSIGNED NOT NULL DEFAULT 5000,
    mode             ENUM('shadow','live','disabled') NOT NULL DEFAULT 'shadow',
    festival_mode    BOOLEAN NOT NULL DEFAULT FALSE,
    status           ENUM('active','maintenance','archived') NOT NULL DEFAULT 'active',
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    PRIMARY KEY (location_id),
    UNIQUE KEY uq_locations_tenant_code (tenant_id, short_code),
    KEY idx_locations_tenant (tenant_id),
    CONSTRAINT fk_locations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

### `counters`
Individual counter windows within a location (a location may have 2–4 physical windows).

```sql
CREATE TABLE counters (
    counter_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    name             VARCHAR(60) NOT NULL,        -- e.g. 'Counter 1', 'Counter 2'
    short_code       VARCHAR(20) NOT NULL,        -- e.g. 'BDV-C1'
    status           ENUM('active','closed','maintenance') NOT NULL DEFAULT 'active',
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    PRIMARY KEY (counter_id),
    UNIQUE KEY uq_counters_location_code (location_id, short_code),
    KEY idx_counters_tenant (tenant_id),
    KEY idx_counters_location (location_id),
    CONSTRAINT fk_counters_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id),
    CONSTRAINT fk_counters_location FOREIGN KEY (location_id) REFERENCES locations(location_id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

## 2. Daily quotas

### `daily_quotas`
The operating quota for a location on a specific date.

```sql
CREATE TABLE daily_quotas (
    daily_quota_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    quota_date       DATE NOT NULL,
    quota            INT UNSIGNED NOT NULL,
    issued           INT UNSIGNED NOT NULL DEFAULT 0,
    opened_at        TIMESTAMP NULL,
    closed_at        TIMESTAMP NULL,
    closed_reason    ENUM('quota_exhausted','operator_override','time_window','system_fault') NULL,
    notes            TEXT NULL,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    PRIMARY KEY (daily_quota_id),
    UNIQUE KEY uq_quota_location_date (location_id, quota_date),
    KEY idx_quota_tenant_date (tenant_id, quota_date),
    CONSTRAINT fk_quota_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id),
    CONSTRAINT fk_quota_location FOREIGN KEY (location_id) REFERENCES locations(location_id)
) ENGINE=InnoDB;
```

---

## 3. Edge infrastructure

### `edge_devices`
Registered Jetson units, one per location.

```sql
CREATE TABLE edge_devices (
    edge_device_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    device_uid       VARCHAR(80) NOT NULL,        -- e.g. 'edge-bdv-01'
    hardware_id      VARCHAR(80) NULL,             -- MAC or serial
    ip_address       VARCHAR(45) NULL,
    firmware_version VARCHAR(40) NULL,
    last_heartbeat_at TIMESTAMP NULL,
    status           ENUM('online','degraded','offline','retired') NOT NULL DEFAULT 'offline',
    config_json      JSON NULL,                    -- runtime inference/stream settings
    api_key_hash     VARCHAR(255) NULL,            -- bcrypt hash of per-device API key
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    PRIMARY KEY (edge_device_id),
    UNIQUE KEY uq_edge_devices_uid (device_uid),
    KEY idx_edge_devices_tenant (tenant_id),
    KEY idx_edge_devices_location (location_id),
    KEY idx_edge_devices_heartbeat (last_heartbeat_at),
    CONSTRAINT fk_edge_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id),
    CONSTRAINT fk_edge_location FOREIGN KEY (location_id) REFERENCES locations(location_id)
) ENGINE=InnoDB;
```

### `cameras`
RTSP streams attached to an edge device with a specific role.

```sql
CREATE TABLE cameras (
    camera_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    edge_device_id   BIGINT UNSIGNED NOT NULL,
    name             VARCHAR(80) NOT NULL,
    role             ENUM('entry_tripwire','counter_window','density','overview') NOT NULL,
    source_type      ENUM('rtsp','webcam') NOT NULL DEFAULT 'rtsp',
    rtsp_url         VARCHAR(400) NOT NULL,
    tripwire_json    JSON NULL,                    -- {"line":[[x1,y1],[x2,y2]],"direction":"down"}
    status           ENUM('active','degraded','disabled') NOT NULL DEFAULT 'active',
    last_frame_at    TIMESTAMP NULL,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    PRIMARY KEY (camera_id),
    KEY idx_cameras_tenant (tenant_id),
    KEY idx_cameras_location (location_id),
    KEY idx_cameras_edge (edge_device_id),
    CONSTRAINT fk_cameras_edge FOREIGN KEY (edge_device_id) REFERENCES edge_devices(edge_device_id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

## 4. Queue events (the audit log)

### `queue_events`
Immutable log of every event detected at any camera. The source of truth for FIFO replay.

```sql
CREATE TABLE queue_events (
    queue_event_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    edge_device_id   BIGINT UNSIGNED NOT NULL,
    camera_id        BIGINT UNSIGNED NULL,
    event_type       ENUM('enter','exit','issue','reverse','reconcile') NOT NULL,
    occurred_at      TIMESTAMP(3) NOT NULL,        -- millisecond precision
    received_at      TIMESTAMP(3) NOT NULL,
    track_id         VARCHAR(60) NULL,             -- ByteTrack id (may repeat across days)
    confidence       DECIMAL(4,3) NULL,            -- 0.000 to 1.000
    metadata_json    JSON NULL,                    -- frame number, bbox, etc.
    created_at       TIMESTAMP NULL,
    PRIMARY KEY (queue_event_id),
    KEY idx_qe_location_time (location_id, occurred_at),
    KEY idx_qe_tenant_time (tenant_id, occurred_at),
    KEY idx_qe_type (event_type),
    KEY idx_qe_edge (edge_device_id),
    CONSTRAINT fk_qe_location FOREIGN KEY (location_id) REFERENCES locations(location_id),
    CONSTRAINT fk_qe_edge FOREIGN KEY (edge_device_id) REFERENCES edge_devices(edge_device_id)
) ENGINE=InnoDB
  PARTITION BY RANGE (TO_DAYS(occurred_at)) (
    -- Daily partitions, rolled by a scheduled job
    PARTITION p_initial VALUES LESS THAN (TO_DAYS('2026-07-01')),
    PARTITION p_maxvalue VALUES LESS THAN MAXVALUE
  );
```

**Partitioning rationale.** Expected ~30K events per location per day, ~100K total. Daily partitions keep queries fast and let us age data to cold storage after 90 days.

---

## 5. Cutoff decisions

### `cutoff_events`
Log of every FIFO decision state change. Useful for shadow-mode comparison and historical analysis.

```sql
CREATE TABLE cutoff_events (
    cutoff_event_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    decided_at       TIMESTAMP(3) NOT NULL,
    mode             ENUM('shadow','live') NOT NULL,
    previous_status  ENUM('open','approaching_cutoff','cutoff_declared','closed') NULL,
    new_status       ENUM('open','approaching_cutoff','cutoff_declared','closed') NOT NULL,
    queue_head       INT UNSIGNED NOT NULL,
    queue_tail       INT UNSIGNED NOT NULL,
    tokens_remaining INT UNSIGNED NOT NULL,
    cutoff_position  INT UNSIGNED NULL,
    issuance_rate    DECIMAL(8,3) NULL,            -- per minute
    arrival_rate     DECIMAL(8,3) NULL,            -- per minute
    reason           VARCHAR(200) NULL,
    created_at       TIMESTAMP NULL,
    PRIMARY KEY (cutoff_event_id),
    KEY idx_ce_location_time (location_id, decided_at),
    KEY idx_ce_tenant_time (tenant_id, decided_at),
    KEY idx_ce_status (new_status),
    CONSTRAINT fk_ce_location FOREIGN KEY (location_id) REFERENCES locations(location_id)
) ENGINE=InnoDB;
```

---

## 6. Announcements

### `announcement_templates`
Pre-approved announcement text per language per event type. Approved by TTD operations.

```sql
CREATE TABLE announcement_templates (
    template_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    code             VARCHAR(60) NOT NULL,           -- e.g. 'cutoff_declared', 'quota_75pct'
    language         ENUM('te','ta','hi','en') NOT NULL,  -- Telugu, Tamil, Hindi, English
    text             TEXT NOT NULL,                  -- with {placeholders}
    audio_file_path  VARCHAR(400) NULL,              -- pre-generated TTS audio
    status           ENUM('draft','approved','retired') NOT NULL DEFAULT 'draft',
    approved_by      BIGINT UNSIGNED NULL,
    approved_at      TIMESTAMP NULL,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    PRIMARY KEY (template_id),
    UNIQUE KEY uq_template_code_lang (tenant_id, code, language)
) ENGINE=InnoDB;
```

### `announcements`
Log of every announcement played.

```sql
CREATE TABLE announcements (
    announcement_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    template_id      BIGINT UNSIGNED NULL,
    trigger_type     ENUM('automatic','manual') NOT NULL,
    triggered_by     BIGINT UNSIGNED NULL,           -- user_id if manual
    text_played      TEXT NOT NULL,                  -- rendered text with placeholders filled
    language         ENUM('te','ta','hi','en') NOT NULL,
    cutoff_event_id  BIGINT UNSIGNED NULL,
    played_at        TIMESTAMP NULL,
    status           ENUM('queued','played','failed') NOT NULL DEFAULT 'queued',
    failure_reason   VARCHAR(400) NULL,
    created_at       TIMESTAMP NULL,
    PRIMARY KEY (announcement_id),
    KEY idx_ann_location_time (location_id, played_at),
    KEY idx_ann_cutoff (cutoff_event_id),
    CONSTRAINT fk_ann_template FOREIGN KEY (template_id) REFERENCES announcement_templates(template_id),
    CONSTRAINT fk_ann_location FOREIGN KEY (location_id) REFERENCES locations(location_id)
) ENGINE=InnoDB;
```

---

## 7. Identity, RBAC, audit

### `users`
Standard Laravel users table with multi-tenancy.

```sql
CREATE TABLE users (
    user_id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id            BIGINT UNSIGNED NOT NULL,
    name                 VARCHAR(120) NOT NULL,
    email                VARCHAR(160) NOT NULL,
    phone                VARCHAR(20) NULL,
    password             VARCHAR(255) NOT NULL,
    email_verified_at    TIMESTAMP NULL,
    last_login_at        TIMESTAMP NULL,
    last_login_ip        VARCHAR(45) NULL,
    fcm_token            VARCHAR(255) NULL,
    status               ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
    remember_token       VARCHAR(100) NULL,
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_tenant_email (tenant_id, email),
    KEY idx_users_tenant (tenant_id)
) ENGINE=InnoDB;
```

### `user_location_assignments`
Which users can access which locations. (Roles via Spatie are tenant-wide; this scopes per location.)

```sql
CREATE TABLE user_location_assignments (
    assignment_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    location_id      BIGINT UNSIGNED NOT NULL,
    can_override     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP NULL,
    PRIMARY KEY (assignment_id),
    UNIQUE KEY uq_user_location (user_id, location_id),
    CONSTRAINT fk_ula_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_ula_location FOREIGN KEY (location_id) REFERENCES locations(location_id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### `audit_logs`
Every operator action that mutates state.

```sql
CREATE TABLE audit_logs (
    audit_log_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id        BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NULL,
    location_id      BIGINT UNSIGNED NULL,
    action           VARCHAR(80) NOT NULL,           -- e.g. 'quota.updated', 'cutoff.overridden'
    entity_type      VARCHAR(80) NULL,
    entity_id        BIGINT UNSIGNED NULL,
    before_json      JSON NULL,
    after_json       JSON NULL,
    ip_address       VARCHAR(45) NULL,
    user_agent       VARCHAR(400) NULL,
    occurred_at      TIMESTAMP NOT NULL,
    PRIMARY KEY (audit_log_id),
    KEY idx_audit_tenant_time (tenant_id, occurred_at),
    KEY idx_audit_user (user_id),
    KEY idx_audit_location (location_id)
) ENGINE=InnoDB;
```

Plus the standard Spatie Permission tables (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) — generated by their migration, not duplicated here.

---

## 8. Indexing strategy

| Query pattern | Index |
| --- | --- |
| "Recent events at location X" | `idx_qe_location_time (location_id, occurred_at)` |
| "Edge device by UID" | `uq_edge_devices_uid (device_uid)` |
| "Stale heartbeats" | `idx_edge_devices_heartbeat (last_heartbeat_at)` |
| "Cutoff history for analytics" | `idx_ce_location_time (location_id, decided_at)` |
| "Find quota for today at location" | `uq_quota_location_date (location_id, quota_date)` |

All foreign keys are indexed (Laravel auto-generates indexes for FKs).

---

## 9. Retention policy

| Table | Retention |
| --- | --- |
| `queue_events` | 90 days hot in MySQL, then archived to S3/Wasabi cold storage |
| `cutoff_events` | 2 years hot (small volume) |
| `announcements` | 1 year hot |
| `audit_logs` | 7 years (regulatory) |
| `edge_devices`, `cameras`, `locations`, `tenants`, `users` | Permanent, soft-deleted via `deleted_at` |

---

## 10. Money columns

There are none in v1 — TrioSense is not a payment system. If billing for AMC or SaaS becomes a thing in v2:

- Always `DECIMAL(15,2)` for money.
- Always store currency code separately (`currency_code CHAR(3)`).
- Never use `FLOAT` or `DOUBLE` for amounts.

---

## 11. Migration ordering

Migrations must be created in this order (the timestamps in filenames enforce it):

1. `tenants`
2. `users` (depends on tenants)
3. Spatie permission tables (depends on users)
4. `locations` (depends on tenants)
5. `counters` (depends on locations)
6. `user_location_assignments` (depends on users + locations)
7. `daily_quotas` (depends on locations)
8. `edge_devices` (depends on locations)
9. `cameras` (depends on edge_devices)
10. `queue_events` (depends on edge_devices)
11. `cutoff_events` (depends on locations)
12. `announcement_templates` (depends on tenants)
13. `announcements` (depends on announcement_templates + locations)
14. `audit_logs` (depends on users + locations)
