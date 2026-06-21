import type { QueueEventItem } from '@/types/api';

export interface BucketRow {
  label: string;
  arrived: number;
  issued: number;
}

export function bucketEventsForTest(
  events: QueueEventItem[],
  bucketMinutes = 15,
): BucketRow[] {
  const buckets = new Map<string, { arrived: number; issued: number }>();

  for (const event of events) {
    const occurred = new Date(event.occurred_at);
    if (Number.isNaN(occurred.getTime())) continue;

    const minutes = occurred.getHours() * 60 + occurred.getMinutes();
    const bucketStart = Math.floor(minutes / bucketMinutes) * bucketMinutes;
    const hour = Math.floor(bucketStart / 60);
    const minute = bucketStart % 60;
    const label = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;

    const row = buckets.get(label) ?? { arrived: 0, issued: 0 };
    if (event.event_type === 'enter') {
      row.arrived += 1;
    } else if (event.event_type === 'issue') {
      row.issued += 1;
    }
    buckets.set(label, row);
  }

  return [...buckets.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([label, counts]) => ({ label, ...counts }));
}
