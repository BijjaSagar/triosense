'use client';

import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { fetchLocationCameras, updateLocationCamera } from '@/lib/api';
import { getToken } from '@/lib/auth';
import type { CameraConfig, TripwireConfig } from '@/types/api';

interface CameraSettingsFormProps {
  locationId: number;
  camera: CameraConfig;
  onSaved?: () => void;
}

const ROLES = [
  { value: 'entry_tripwire', label: 'Entry tripwire' },
  { value: 'counter_window', label: 'Counter window' },
  { value: 'density', label: 'Density' },
  { value: 'overview', label: 'Overview' },
] as const;

function defaultTripwire(camera: CameraConfig): TripwireConfig {
  return (
    camera.tripwire ?? {
      line: [
        [200, 360],
        [1080, 360],
      ],
      direction: 'down',
    }
  );
}

export function CameraSettingsForm({ locationId, camera, onSaved }: CameraSettingsFormProps) {
  const [name, setName] = useState(camera.name);
  const [sourceType, setSourceType] = useState<'rtsp' | 'webcam'>(camera.source_type);
  const [rtspUrl, setRtspUrl] = useState(camera.rtsp_url);
  const [role, setRole] = useState(camera.role);
  const [tripwire, setTripwire] = useState<TripwireConfig>(() => defaultTripwire(camera));
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    setName(camera.name);
    setSourceType(camera.source_type);
    setRtspUrl(camera.rtsp_url);
    setRole(camera.role);
    setTripwire(defaultTripwire(camera));
  }, [camera]);

  async function save() {
    const token = getToken();
    if (!token) {
      setMessage('Not authenticated');
      return;
    }

    setLoading(true);
    setMessage(null);

    try {
      await updateLocationCamera(locationId, camera.camera_id, token, {
        name,
        source_type: sourceType,
        rtsp_url: rtspUrl,
        role,
        tripwire_json: role === 'entry_tripwire' ? tripwire : null,
      });
      setMessage('Camera settings saved');
      onSaved?.();
    } catch (err: unknown) {
      setMessage(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setLoading(false);
    }
  }

  function updateLinePoint(pointIndex: 0 | 1, coordIndex: 0 | 1, value: string) {
    setTripwire((prev) => {
      const line = prev.line.map((point, idx) =>
        idx === pointIndex
          ? point.map((coord, cIdx) => (cIdx === coordIndex ? Number(value) : coord)) as [
              number,
              number,
            ]
          : point,
      ) as TripwireConfig['line'];
      return { ...prev, line };
    });
  }

  return (
    <div className="rounded-xl border border-border p-4">
      <h3 className="font-semibold text-maroon-700">
        Camera #{camera.camera_id}: {camera.name}
      </h3>
      <p className="mt-1 text-sm text-muted-foreground">
        Edge device {camera.edge_device_id} · status {camera.status}
      </p>

      <div className="mt-4 grid gap-3 md:grid-cols-2">
        <label className="text-sm">
          <span className="mb-1 block font-medium">Name</span>
          <input
            className="w-full rounded-md border border-input px-3 py-2 text-sm"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
        </label>

        <label className="text-sm">
          <span className="mb-1 block font-medium">Role</span>
          <select
            className="w-full rounded-md border border-input px-3 py-2 text-sm"
            value={role}
            onChange={(e) => setRole(e.target.value as CameraConfig['role'])}
          >
            {ROLES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="text-sm">
          <span className="mb-1 block font-medium">Source type</span>
          <select
            className="w-full rounded-md border border-input px-3 py-2 text-sm"
            value={sourceType}
            onChange={(e) => setSourceType(e.target.value as 'rtsp' | 'webcam')}
          >
            <option value="webcam">Webcam (device index)</option>
            <option value="rtsp">RTSP URL</option>
          </select>
        </label>

        <label className="text-sm">
          <span className="mb-1 block font-medium">
            {sourceType === 'webcam' ? 'Device index' : 'RTSP URL'}
          </span>
          <input
            className="w-full rounded-md border border-input px-3 py-2 text-sm"
            value={rtspUrl}
            placeholder={sourceType === 'webcam' ? '0' : 'rtsp://user:pass@host:554/stream'}
            onChange={(e) => setRtspUrl(e.target.value)}
          />
        </label>
      </div>

      {role === 'entry_tripwire' && (
        <div className="mt-4 rounded-lg bg-muted/40 p-3">
          <p className="text-sm font-medium text-maroon-700">Tripwire line</p>
          <div className="mt-2 grid gap-2 sm:grid-cols-2">
            {[0, 1].map((pointIndex) => (
              <div key={pointIndex} className="flex gap-2">
                <input
                  type="number"
                  className="w-full rounded-md border border-input px-2 py-1 text-sm"
                  value={tripwire.line[pointIndex as 0 | 1][0]}
                  onChange={(e) => updateLinePoint(pointIndex as 0 | 1, 0, e.target.value)}
                  placeholder={`x${pointIndex + 1}`}
                />
                <input
                  type="number"
                  className="w-full rounded-md border border-input px-2 py-1 text-sm"
                  value={tripwire.line[pointIndex as 0 | 1][1]}
                  onChange={(e) => updateLinePoint(pointIndex as 0 | 1, 1, e.target.value)}
                  placeholder={`y${pointIndex + 1}`}
                />
              </div>
            ))}
          </div>
          <label className="mt-2 block text-sm">
            <span className="mb-1 block font-medium">IN direction</span>
            <select
              className="w-full rounded-md border border-input px-3 py-2 text-sm"
              value={tripwire.direction}
              onChange={(e) =>
                setTripwire((prev) => ({
                  ...prev,
                  direction: e.target.value as TripwireConfig['direction'],
                }))
              }
            >
              {(['up', 'down', 'left', 'right'] as const).map((dir) => (
                <option key={dir} value={dir}>
                  {dir}
                </option>
              ))}
            </select>
          </label>
          <p className="mt-2 text-xs text-muted-foreground">
            Tip: run{' '}
            <code className="rounded bg-muted px-1">make edge-calibrate</code> to draw the line on
            a live frame.
          </p>
        </div>
      )}

      <div className="mt-4 flex items-center gap-3">
        <Button disabled={loading} onClick={save}>
          Save camera
        </Button>
        {message && <p className="text-sm text-muted-foreground">{message}</p>}
      </div>
    </div>
  );
}

interface CameraSettingsPanelProps {
  locationId: number;
}

export function CameraSettingsPanel({ locationId }: CameraSettingsPanelProps) {
  const [cameras, setCameras] = useState<CameraConfig[]>([]);
  const [error, setError] = useState<string | null>(null);

  function reload() {
    const token = getToken();
    if (!token) {
      setError('Not authenticated');
      return;
    }

    fetchLocationCameras(locationId, token)
      .then((items) => {
        setCameras(items);
        setError(null);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load cameras');
      });
  }

  useEffect(() => {
    reload();
  }, [locationId]);

  if (error) {
    return <div className="rounded-lg bg-red-50 px-4 py-3 text-red-800">{error}</div>;
  }

  if (cameras.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-border p-8 text-center text-muted-foreground">
        No cameras configured for this location.
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {cameras.map((camera) => (
        <CameraSettingsForm
          key={camera.camera_id}
          locationId={locationId}
          camera={camera}
          onSaved={reload}
        />
      ))}
    </div>
  );
}
