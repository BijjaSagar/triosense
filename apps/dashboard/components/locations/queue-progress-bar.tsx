import { cn } from '@/lib/utils';

interface QueueProgressBarProps {
  head: number;
  tail: number;
  cutoff: number | null;
  quota: number;
}

export function QueueProgressBar({ head, tail, cutoff, quota }: QueueProgressBarProps) {
  const max = Math.max(quota, tail, 1);
  const headPct = (head / max) * 100;
  const tailPct = (tail / max) * 100;
  const cutoffPct = cutoff !== null ? (cutoff / max) * 100 : null;

  return (
    <div className="space-y-2">
      <div className="relative h-3 w-full overflow-hidden rounded-full bg-muted">
        <div
          className="absolute inset-y-0 left-0 rounded-full bg-gold-400/60"
          style={{ width: `${tailPct}%` }}
        />
        <div
          className="absolute inset-y-0 left-0 w-1 bg-maroon-700"
          style={{ left: `${headPct}%` }}
        />
        {cutoffPct !== null && (
          <div
            className="absolute inset-y-0 w-0.5 bg-red-600"
            style={{ left: `${cutoffPct}%` }}
          />
        )}
      </div>
      <div className="flex justify-between text-xs text-muted-foreground">
        <span>Head #{head.toLocaleString()}</span>
        <span>Tail #{tail.toLocaleString()}</span>
        {cutoff !== null && <span>Cutoff #{cutoff.toLocaleString()}</span>}
      </div>
    </div>
  );
}
