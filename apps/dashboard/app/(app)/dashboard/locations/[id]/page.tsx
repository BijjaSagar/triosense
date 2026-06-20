'use client';

import { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { CrossCounterBanner } from '@/components/locations/cross-counter-banner';
import { LocationCard } from '@/components/locations/location-card';
import { OverridePanel } from '@/components/locations/override-panel';
import { ShadowModeBanner } from '@/components/locations/shadow-mode-banner';
import { ShadowPerformanceChart } from '@/components/locations/shadow-performance-chart';
import { fetchAnnouncements, fetchLocationEvents, fetchLocationState } from '@/lib/api';
import { getToken } from '@/lib/auth';
import type { AnnouncementItem, LocationState, QueueEventItem } from '@/types/api';

export default function LocationDetailPage() {
  const params = useParams<{ id: string }>();
  const locationId = Number(params.id);
  const [state, setState] = useState<LocationState | null>(null);
  const [events, setEvents] = useState<QueueEventItem[]>([]);
  const [announcements, setAnnouncements] = useState<AnnouncementItem[]>([]);
  const [tab, setTab] = useState<'live' | 'shadow' | 'announcements'>('live');
  const [error, setError] = useState<string | null>(null);

  function reload() {
    const token = getToken();
    if (!token || Number.isNaN(locationId)) return;

    Promise.all([
      fetchLocationState(locationId, token),
      fetchLocationEvents(locationId, token, 50),
      fetchAnnouncements(locationId, token),
    ])
      .then(([locationState, paginated, ann]) => {
        setState(locationState);
        setEvents(paginated.items);
        setAnnouncements(ann.items);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load location');
      });
  }

  useEffect(() => {
    reload();
  }, [locationId]);

  if (error) {
    return <div className="rounded-lg bg-red-50 px-4 py-3 text-red-800">{error}</div>;
  }

  if (!state) {
    return <div className="h-96 animate-pulse rounded-2xl bg-muted" />;
  }

  return (
    <div className="space-y-6">
      <CrossCounterBanner />
      <ShadowModeBanner mode={state.mode ?? 'live'} />

      <div className="flex gap-2 border-b border-border">
        {(['live', 'shadow', 'announcements'] as const).map((key) => (
          <button
            key={key}
            type="button"
            className={`px-4 py-2 text-sm font-medium ${
              tab === key
                ? 'border-b-2 border-maroon-700 text-maroon-700'
                : 'text-muted-foreground'
            }`}
            onClick={() => setTab(key)}
          >
            {key === 'live' && 'Live view'}
            {key === 'shadow' && 'Shadow performance'}
            {key === 'announcements' && 'Announcements'}
          </button>
        ))}
      </div>

      {tab === 'live' && (
        <>
          <LocationCard initialState={state} />
          <OverridePanel locationId={locationId} onApplied={reload} />
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
        </>
      )}

      {tab === 'shadow' && <ShadowPerformanceChart locationId={locationId} />}

      {tab === 'announcements' && (
        <section>
          <h2 className="text-lg font-semibold text-maroon-700">Announcement history</h2>
          <div className="mt-4 overflow-hidden rounded-xl border border-border">
            <table className="w-full text-left text-sm">
              <thead className="bg-muted text-muted-foreground">
                <tr>
                  <th className="px-4 py-2">Language</th>
                  <th className="px-4 py-2">Text</th>
                  <th className="px-4 py-2">Status</th>
                  <th className="px-4 py-2">Played</th>
                </tr>
              </thead>
              <tbody>
                {announcements.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-6 text-center text-muted-foreground">
                      No announcements yet
                    </td>
                  </tr>
                ) : (
                  announcements.map((item) => (
                    <tr key={item.announcement_id} className="border-t border-border">
                      <td className="px-4 py-2 uppercase">{item.language}</td>
                      <td className="px-4 py-2">{item.text_played}</td>
                      <td className="px-4 py-2">{item.status}</td>
                      <td className="px-4 py-2">{item.played_at ?? '—'}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  );
}
