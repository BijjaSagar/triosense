'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { EdgePreviewPanel } from '@/components/locations/edge-preview-panel';
import { LocationCard } from '@/components/locations/location-card';
import { fetchLocationState } from '@/lib/api';
import type { LocationState } from '@/types/api';
import { useEffect, useState } from 'react';

export default function LocationPreviewPage() {
  const params = useParams<{ id: string }>();
  const locationId = Number(params.id);
  const [state, setState] = useState<LocationState | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (Number.isNaN(locationId)) {
      setError('Invalid location');
      return;
    }

    fetchLocationState(locationId)
      .then(setState)
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
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-maroon-700">Live camera preview</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Webcam feed with YOLO person detection and tripwire counters for {state.location_name}.
          </p>
        </div>
        <Link
          href={`/dashboard/locations/${locationId}`}
          className="text-sm font-medium text-maroon-700 hover:underline"
        >
          Back to live view
        </Link>
      </div>

      <EdgePreviewPanel />
      <LocationCard initialState={state} compact />
    </div>
  );
}
