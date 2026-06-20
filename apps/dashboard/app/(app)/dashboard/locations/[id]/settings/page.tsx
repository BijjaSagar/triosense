'use client';

import Link from 'next/link';
import { useParams } from 'next/navigation';
import { CameraSettingsPanel } from '@/components/locations/camera-settings-form';

export default function LocationCameraSettingsPage() {
  const params = useParams<{ id: string }>();
  const locationId = Number(params.id);

  if (Number.isNaN(locationId)) {
    return <div className="text-red-800">Invalid location ID</div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-maroon-700">Camera settings</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Configure RTSP/webcam sources, roles, and tripwire lines for location {locationId}.
          </p>
        </div>
        <Link
          href={`/dashboard/locations/${locationId}`}
          className="text-sm font-medium text-maroon-700 hover:underline"
        >
          Back to live view
        </Link>
      </div>

      <CameraSettingsPanel locationId={locationId} />
    </div>
  );
}
