'use client';

import { cn } from '@/lib/utils';

interface ShadowModeBannerProps {
  mode: string;
}

export function ShadowModeBanner({ mode }: ShadowModeBannerProps) {
  if (mode !== 'shadow') {
    return null;
  }

  return (
    <div
      className={cn(
        'rounded-lg border border-amber-300 bg-amber-50 px-4 py-3',
        'text-sm font-semibold uppercase tracking-wide text-amber-900',
      )}
      role="status"
    >
      Shadow mode — predictions only; no entry closure or PA announcements
    </div>
  );
}
