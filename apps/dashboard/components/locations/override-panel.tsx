'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { applyCutoffOverride } from '@/lib/api';

interface OverridePanelProps {
  locationId: number;
  onApplied?: () => void;
}

export function OverridePanel({ locationId, onApplied }: OverridePanelProps) {
  const [reason, setReason] = useState('');
  const [cutoffPosition, setCutoffPosition] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  async function submit(action: 'force_open' | 'force_close' | 'set_cutoff') {
    if (reason.length < 5) {
      setMessage('Reason must be at least 5 characters');
      return;
    }

    setLoading(true);
    setMessage(null);

    try {
      await applyCutoffOverride(locationId, {
        action,
        reason,
        cutoff_position: action === 'set_cutoff' ? Number(cutoffPosition) : undefined,
      });
      setMessage('Override applied and audit-logged');
      onApplied?.();
    } catch (err: unknown) {
      setMessage(err instanceof Error ? err.message : 'Override failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="rounded-xl border border-border p-4">
      <h3 className="font-semibold text-maroon-700">Manual override</h3>
      <p className="mt-1 text-sm text-muted-foreground">
        All overrides are audit-logged with your user ID.
      </p>

      <textarea
        className="mt-3 w-full rounded-md border border-input px-3 py-2 text-sm"
        placeholder="Reason (required, min 5 chars)"
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        rows={2}
      />

      <div className="mt-3 flex flex-wrap gap-2">
        <input
          type="number"
          className="w-40 rounded-md border border-input px-3 py-2 text-sm"
          placeholder="Cutoff position"
          value={cutoffPosition}
          onChange={(e) => setCutoffPosition(e.target.value)}
        />
        <Button disabled={loading} onClick={() => submit('set_cutoff')} variant="outline">
          Set cutoff
        </Button>
        <Button disabled={loading} onClick={() => submit('force_open')} variant="outline">
          Force open
        </Button>
        <Button disabled={loading} onClick={() => submit('force_close')} variant="destructive">
          Force close
        </Button>
      </div>

      {message && <p className="mt-3 text-sm text-muted-foreground">{message}</p>}
    </div>
  );
}
