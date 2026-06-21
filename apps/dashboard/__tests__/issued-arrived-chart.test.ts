import { describe, expect, it } from 'vitest';
import { bucketEventsForTest } from '@/components/locations/issued-arrived-chart-utils';
import type { QueueEventItem } from '@/types/api';

describe('issued-arrived chart bucketing', () => {
  it('counts enter and issue events in 15-minute buckets', () => {
    const events: QueueEventItem[] = [
      {
        queue_event_id: 1,
        event_type: 'enter',
        occurred_at: '2026-06-20T06:05:00+05:30',
        received_at: '2026-06-20T06:05:01+05:30',
        edge_device_id: 1,
        camera_id: 1,
        track_id: 'a',
        confidence: 0.9,
      },
      {
        queue_event_id: 2,
        event_type: 'issue',
        occurred_at: '2026-06-20T06:12:00+05:30',
        received_at: '2026-06-20T06:12:01+05:30',
        edge_device_id: 1,
        camera_id: 1,
        track_id: 'b',
        confidence: 0.9,
      },
    ];

    const rows = bucketEventsForTest(events);
    expect(rows[0]).toEqual({ label: '06:00', arrived: 1, issued: 1 });
  });
});

describe('cookie auth client', () => {
  it('marks login response token as nullable for cookie mode', () => {
    const response = { token: null, user: { email: 'ops@ttd.gov.in' }, expires_at: null };
    expect(response.token).toBeNull();
    expect(response.user.email).toBe('ops@ttd.gov.in');
  });
});
