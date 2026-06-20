'use client';

import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/card';

const PREVIEW_BASE =
  process.env.NEXT_PUBLIC_EDGE_PREVIEW_URL ?? 'http://127.0.0.1:8766';

interface EdgePreviewStats {
  person_count: number;
  enter_count: number;
  exit_count: number;
  fps: number;
  camera_id: number;
  status: string;
}

export function EdgePreviewPanel() {
  const [stats, setStats] = useState<EdgePreviewStats | null>(null);
  const [offline, setOffline] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function pollStats() {
      try {
        const res = await fetch(`${PREVIEW_BASE}/api/stats`, { cache: 'no-store' });
        if (!res.ok) {
          throw new Error(`Preview stats failed: ${res.status}`);
        }
        const data = (await res.json()) as EdgePreviewStats;
        if (!cancelled) {
          setStats(data);
          setOffline(false);
        }
      } catch {
        if (!cancelled) {
          setOffline(true);
        }
      }
    }

    pollStats();
    const timer = window.setInterval(pollStats, 1000);
    return () => {
      cancelled = true;
      window.clearInterval(timer);
    };
  }, []);

  return (
    <Card className="overflow-hidden p-0">
      <div className="border-b border-border px-4 py-3">
        <h2 className="text-lg font-semibold text-maroon-700">Edge camera preview</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Live feed from <code className="text-xs">{PREVIEW_BASE}</code>. Run{' '}
          <code className="text-xs">make edge-webcam</code> in another terminal.
        </p>
      </div>

      <div className="relative bg-black">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={`${PREVIEW_BASE}/stream.mjpg`}
          alt="Edge webcam preview with person detection"
          className="block w-full"
        />
        {offline && (
          <div className="absolute inset-0 flex items-center justify-center bg-black/70 p-6 text-center text-sm text-white">
            Preview offline — start the edge pipeline with{' '}
            <code className="mx-1 rounded bg-white/10 px-1">make edge-webcam</code>
          </div>
        )}
      </div>

      <div className="grid grid-cols-2 gap-4 px-4 py-4 md:grid-cols-4">
        <PreviewStat label="Persons in frame" value={stats?.person_count ?? '—'} />
        <PreviewStat label="Tripwire IN" value={stats?.enter_count ?? '—'} />
        <PreviewStat label="Tripwire OUT" value={stats?.exit_count ?? '—'} />
        <PreviewStat label="Inference FPS" value={stats ? stats.fps.toFixed(1) : '—'} />
      </div>
    </Card>
  );
}

function PreviewStat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-lg bg-muted px-3 py-2 text-center">
      <p className="text-2xl font-bold text-maroon-700">{value}</p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  );
}
