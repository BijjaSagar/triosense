'use client';

import { useEffect, useState } from 'react';
import { CrossCounterBanner } from '@/components/locations/cross-counter-banner';
import { LocationCard } from '@/components/locations/location-card';
import { fetchLocationState } from '@/lib/api';
import { getToken } from '@/lib/auth';
import type { LocationState } from '@/types/api';

const LOCATION_IDS = [1, 2, 3];

export default function DashboardPage() {
  const [states, setStates] = useState<LocationState[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = getToken();
    if (!token) return;

    Promise.all(LOCATION_IDS.map((id) => fetchLocationState(id, token)))
      .then(setStates)
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load locations');
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        {LOCATION_IDS.map((id) => (
          <div
            key={id}
            className="h-64 animate-pulse rounded-2xl border border-border bg-muted"
          />
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-lg bg-red-50 px-4 py-3 text-red-800">{error}</div>
    );
  }

  return (
    <div>
      <CrossCounterBanner />
      <h1 className="text-2xl font-bold text-maroon-700">Live counters</h1>
      <p className="mt-1 text-sm text-muted-foreground">
        Real-time state for all three TTD SSD locations
      </p>

      <div className="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        {states.map((state) => (
          <LocationCard key={state.location_id} initialState={state} compact />
        ))}
      </div>
    </div>
  );
}
