'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { LocationCard } from '@/components/locations/location-card';
import { fetchLocationEvents, fetchLocationState } from '@/lib/api';
import { getToken } from '@/lib/auth';
import type { LocationState, QueueEventItem } from '@/types/api';

export default function LocationDetailPage() {
  const params = useParams<{ id: string }>();
  const locationId = Number(params.id);
  const [state, setState] = useState<LocationState | null>(null);
  const [events, setEvents] = useState<QueueEventItem[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = getToken();
    if (!token || Number.isNaN(locationId)) return;

    Promise.all([
      fetchLocationState(locationId, token),
      fetchLocationEvents(locationId, token, 50),
    ])
      .then(([locationState, paginated]) => {
        setState(locationState);
        setEvents(paginated.items);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load location');
      });
  }, [locationId]);

  if (error) {
    return <div className="rounded-lg bg-red-50 px-4 py-3 text-red-800">{error}</div>;
  }

  if (!state) {
    return <div className="h-96 animate-pulse rounded-2xl bg-muted" />;
  }

  return (
    <div className="space-y-8">
      <LocationCard initialState={state} />

      <section>
        <h2 className="text-lg font-semibold text-maroon-700">Recent events</h2>
        <div className="mt-4 overflow-hidden rounded-xl border border-border">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted text-muted-foreground">
              <tr>
                <th className="px-4 py-2">Type</th>
                <th className="px-4 py-2">Occurred</th>
                <th className="px-4 py-2">Track</th>
                <th className="px-4 py-2">Confidence</th>
              </tr>
            </thead>
            <tbody>
              {events.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-muted-foreground">
                    No events yet
                  </td>
                </tr>
              ) : (
                events.map((event) => (
                  <tr key={event.queue_event_id} className="border-t border-border">
                    <td className="px-4 py-2 font-medium uppercase">{event.event_type}</td>
                    <td className="px-4 py-2">{event.occurred_at}</td>
                    <td className="px-4 py-2">{event.track_id ?? '—'}</td>
                    <td className="px-4 py-2">
                      {event.confidence !== null ? event.confidence.toFixed(2) : '—'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
