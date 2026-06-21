'use client';

import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import type { QueueEventItem } from '@/types/api';
import { bucketEventsForTest as bucketEvents } from '@/components/locations/issued-arrived-chart-utils';

interface IssuedArrivedChartProps {
  events: QueueEventItem[];
}

export function IssuedArrivedChart({ events }: IssuedArrivedChartProps) {
  const chartData = bucketEvents(events);

  if (chartData.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        No enter/issue events yet — chart appears once traffic is recorded.
      </p>
    );
  }

  return (
    <div className="h-72 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={chartData}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="label" />
          <YAxis allowDecimals={false} />
          <Tooltip />
          <Legend />
          <Line
            type="monotone"
            dataKey="arrived"
            stroke="#2563eb"
            name="Arrived (enter)"
            strokeWidth={2}
            dot={false}
          />
          <Line
            type="monotone"
            dataKey="issued"
            stroke="#6D1A2C"
            name="Issued"
            strokeWidth={2}
            dot={false}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
