'use client';

import { useEffect, useState } from 'react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { fetchCutoffAccuracy } from '@/lib/api';
import { getToken } from '@/lib/auth';
import type { CutoffAccuracyReport } from '@/types/api';

interface ShadowPerformanceChartProps {
  locationId: number;
}

export function ShadowPerformanceChart({ locationId }: ShadowPerformanceChartProps) {
  const [report, setReport] = useState<CutoffAccuracyReport | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = getToken();
    if (!token) return;

    fetchCutoffAccuracy(locationId, token)
      .then(setReport)
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load accuracy data');
      });
  }, [locationId]);

  if (error) {
    return <p className="text-sm text-red-700">{error}</p>;
  }

  if (!report) {
    return <div className="h-64 animate-pulse rounded-xl bg-muted" />;
  }

  const chartData = report.daily.map((row) => ({
    date: row.date.slice(5),
    delta: row.delta_positions ?? 0,
    within: row.within_tolerance ? 1 : 0,
  }));

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-3 gap-4 text-center">
        <div className="rounded-lg bg-muted p-3">
          <p className="text-2xl font-bold text-maroon-700">
            {report.summary.days_with_predictions}
          </p>
          <p className="text-xs text-muted-foreground">Days with predictions</p>
        </div>
        <div className="rounded-lg bg-muted p-3">
          <p className="text-2xl font-bold text-maroon-700">
            {report.summary.days_within_tolerance}
          </p>
          <p className="text-xs text-muted-foreground">Within ±10 positions</p>
        </div>
        <div className="rounded-lg bg-muted p-3">
          <p className="text-2xl font-bold text-maroon-700">
            {report.summary.median_delta ?? '—'}
          </p>
          <p className="text-xs text-muted-foreground">Median delta</p>
        </div>
      </div>

      <div className="h-64 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={chartData}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="date" />
            <YAxis allowDecimals={false} />
            <Tooltip />
            <Bar dataKey="delta" fill="#6D1A2C" name="Position delta" />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
