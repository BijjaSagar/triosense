'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import type { LocationSummary } from '@/types/api';

const FALLBACK_LOCATIONS: LocationSummary[] = [
  { location_id: 1, name: 'Vishnu Nivasam', short_code: 'VSN', mode: 'shadow', status: 'active' },
  { location_id: 2, name: 'Srinivasam Complex', short_code: 'SRN', mode: 'shadow', status: 'active' },
  { location_id: 3, name: 'Bhudevi Complex', short_code: 'BDV', mode: 'shadow', status: 'active' },
];

interface AppSidebarProps {
  locations?: LocationSummary[];
}

export function AppSidebar({ locations = FALLBACK_LOCATIONS }: AppSidebarProps) {
  const pathname = usePathname();

  return (
    <aside className="flex w-64 flex-col border-r border-border bg-card p-4">
      <div className="mb-8">
        <p className="text-xs font-semibold uppercase tracking-widest text-gold-400">
          TrioSense
        </p>
        <h1 className="text-xl font-bold text-maroon-700">TTD Control Room</h1>
      </div>

      <nav className="flex flex-1 flex-col gap-1">
        <Link
          href="/dashboard"
          className={cn(
            'rounded-lg px-3 py-2 text-sm font-medium',
            pathname === '/dashboard'
              ? 'bg-maroon-700 text-white'
              : 'text-foreground hover:bg-muted',
          )}
        >
          All locations
        </Link>

        <p className="mt-4 px-3 text-xs font-semibold uppercase text-muted-foreground">
          Counters
        </p>
        {locations.map((location) => (
          <Link
            key={location.location_id}
            href={`/dashboard/locations/${location.location_id}`}
            className={cn(
              'rounded-lg px-3 py-2 text-sm',
              pathname === `/dashboard/locations/${location.location_id}`
                ? 'bg-maroon-700 text-white'
                : 'text-foreground hover:bg-muted',
            )}
          >
            <span className="font-medium">{location.short_code}</span>
            <span className="ml-2 text-muted-foreground">{location.name}</span>
          </Link>
        ))}
      </nav>
    </aside>
  );
}
