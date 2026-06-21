'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { AppSidebar } from '@/components/shared/app-sidebar';
import { fetchLocations, fetchMe } from '@/lib/api';
import { markAuthenticated, markLoggedOut } from '@/lib/auth';
import type { LocationSummary } from '@/types/api';

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [locations, setLocations] = useState<LocationSummary[]>([]);

  useEffect(() => {
    fetchMe()
      .then(() => {
        markAuthenticated();
        return fetchLocations();
      })
      .then(setLocations)
      .catch(() => {
        markLoggedOut();
        router.replace('/login');
      });
  }, [router]);

  return (
    <div className="flex min-h-screen">
      <AppSidebar locations={locations.length > 0 ? locations : undefined} />
      <main className="flex-1 overflow-auto p-6">{children}</main>
    </div>
  );
}
