'use client';

import Link from 'next/link';
import { Card } from '@/components/ui/card';
import { QueueProgressBar } from '@/components/locations/queue-progress-bar';
import { ShadowModeBanner } from '@/components/locations/shadow-mode-banner';
import { StatusBadge } from '@/components/locations/status-badge';
import { useLocationState } from '@/hooks/use-location-state';
import { cn, isStale } from '@/lib/utils';
import type { LocationState } from '@/types/api';

interface LocationCardProps {
  initialState: LocationState;
  compact?: boolean;
}

export function LocationCard({ initialState, compact = false }: LocationCardProps) {
  const state = useLocationState(initialState.location_id, initialState);
  const stale = isStale(state.as_of);
  const showReconnecting = state.connectionState !== 'connected';

  return (
    <Card className={cn(compact && 'p-4')}>
      {state.mode === 'shadow' && (
        <div className="mb-3">
          <ShadowModeBanner mode={state.mode} />
        </div>
      )}
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-bold text-maroon-700">
            {state.location_name}
          </h2>
          <p className="text-sm text-muted-foreground">{state.short_code}</p>
        </div>
        <StatusBadge status={state.status} />
      </div>

      <p className={cn('mt-4 font-bold text-maroon-700', compact ? 'text-3xl' : 'text-5xl')}>
        {state.tokens_remaining.toLocaleString()}
      </p>
      <p className="text-sm text-muted-foreground">tokens remaining</p>

      <div className="mt-4">
        <QueueProgressBar
          head={state.queue_head}
          tail={state.queue_tail}
          cutoff={state.cutoff_position}
          quota={state.quota}
        />
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        {state.edge_devices.map((device) => (
          <span
            key={device.device_uid}
            className={cn(
              'rounded-full px-2 py-0.5 text-xs font-medium',
              device.status === 'online'
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-red-50 text-red-700',
            )}
          >
            {device.device_uid}: {device.status}
          </span>
        ))}
      </div>

      {(stale || showReconnecting) && (
        <div
          className={cn(
            'mt-4 rounded-lg px-3 py-2 text-sm',
            showReconnecting
              ? 'bg-amber-50 text-amber-900'
              : 'bg-red-50 text-red-800',
          )}
        >
          {showReconnecting ? 'Reconnecting…' : 'Data may be stale — last update > 5s ago'}
        </div>
      )}

      {!compact && (
        <Link
          href={`/dashboard/locations/${state.location_id}`}
          className="mt-4 inline-block text-sm font-medium text-maroon-700 hover:underline"
        >
          View details →
        </Link>
      )}
    </Card>
  );
}
