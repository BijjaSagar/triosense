'use client';

import { useEffect, useState } from 'react';
import { fetchCrossCounterRecommendations } from '@/lib/api';
import { getToken } from '@/lib/auth';
import type { CrossCounterRecommendation } from '@/types/api';

export function CrossCounterBanner() {
  const [items, setItems] = useState<CrossCounterRecommendation[]>([]);

  useEffect(() => {
    const token = getToken();
    if (!token) return;

    fetchCrossCounterRecommendations(token)
      .then((data) => setItems(data.recommendations))
      .catch(() => setItems([]));
  }, []);

  if (items.length === 0) {
    return null;
  }

  return (
    <div className="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
      <p className="font-semibold">Cross-counter recommendation</p>
      <ul className="mt-2 list-disc pl-5">
        {items.map((item) => (
          <li key={`${item.source_location_id}-${item.target_location_id}`}>
            {item.message}
          </li>
        ))}
      </ul>
    </div>
  );
}
