import type {
  ApiErrorResponse,
  ApiResponse,
  LocationState,
  LocationSummary,
  LoginResponse,
  PaginatedEvents,
} from '@/types/api';
import {
  apiBaseUrl,
  cookieAuthHeaders,
  defaultHeaders,
  ensureCsrfCookie,
} from '@/lib/api-client';

const BASE = apiBaseUrl();

async function parseResponse<T>(res: Response): Promise<T> {
  const json = (await res.json()) as ApiResponse<T> | ApiErrorResponse;

  if (!res.ok || !json.success) {
    const message =
      'error' in json ? json.error.message : `Request failed: ${res.status}`;
    throw new Error(message);
  }

  return json.data;
}

export async function login(
  email: string,
  password: string,
): Promise<LoginResponse> {
  await ensureCsrfCookie();

  const res = await fetch(`${BASE}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      ...defaultHeaders(),
      'X-TrioSense-Auth': 'cookie',
    },
    credentials: 'include',
    body: JSON.stringify({ email, password }),
  });

  return parseResponse<LoginResponse>(res);
}

export async function logout(): Promise<void> {
  await ensureCsrfCookie();

  await fetch(`${BASE}/api/v1/auth/logout`, {
    method: 'POST',
    headers: cookieAuthHeaders(),
    credentials: 'include',
  });
}

export async function fetchMe(): Promise<LoginResponse['user']> {
  const res = await fetch(`${BASE}/api/v1/auth/me`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function fetchLocations(): Promise<LocationSummary[]> {
  const res = await fetch(`${BASE}/api/v1/locations`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  const data = await parseResponse<{ locations: LocationSummary[] }>(res);
  return data.locations;
}

export async function fetchLocationState(
  locationId: number,
): Promise<LocationState> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/state`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  return parseResponse<LocationState>(res);
}

export async function fetchLocationEvents(
  locationId: number,
  perPage = 50,
): Promise<PaginatedEvents> {
  const res = await fetch(
    `${BASE}/api/v1/locations/${locationId}/events?per_page=${perPage}`,
    {
      headers: cookieAuthHeaders(),
      credentials: 'include',
      cache: 'no-store',
    },
  );

  return parseResponse<PaginatedEvents>(res);
}

export async function fetchCutoffAccuracy(
  locationId: number,
): Promise<import('@/types/api').CutoffAccuracyReport> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cutoff-accuracy`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function fetchAnnouncements(
  locationId: number,
): Promise<{ items: import('@/types/api').AnnouncementItem[] }> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/announcements`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function applyCutoffOverride(
  locationId: number,
  body: { action: string; reason: string; cutoff_position?: number },
): Promise<LocationState> {
  await ensureCsrfCookie();

  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cutoff/override`, {
    method: 'POST',
    headers: cookieAuthHeaders(),
    credentials: 'include',
    body: JSON.stringify(body),
  });

  return parseResponse<LocationState>(res);
}

export async function updateLocation(
  locationId: number,
  body: { mode?: string; festival_mode?: boolean },
): Promise<{ location_id: number; mode: string; festival_mode: boolean }> {
  await ensureCsrfCookie();

  const res = await fetch(`${BASE}/api/v1/locations/${locationId}`, {
    method: 'PATCH',
    headers: cookieAuthHeaders(),
    credentials: 'include',
    body: JSON.stringify(body),
  });

  return parseResponse(res);
}

export async function fetchCrossCounterRecommendations(): Promise<{
  recommendations: import('@/types/api').CrossCounterRecommendation[];
}> {
  const res = await fetch(`${BASE}/api/v1/cross-counter/recommendations`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function fetchLocationCameras(
  locationId: number,
): Promise<import('@/types/api').CameraConfig[]> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cameras`, {
    headers: cookieAuthHeaders(),
    credentials: 'include',
    cache: 'no-store',
  });

  const data = await parseResponse<{ cameras: import('@/types/api').CameraConfig[] }>(res);
  return data.cameras;
}

export async function updateLocationCamera(
  locationId: number,
  cameraId: number,
  body: {
    name?: string;
    role?: string;
    source_type?: string;
    rtsp_url?: string;
    tripwire_json?: import('@/types/api').TripwireConfig | null;
    status?: string;
  },
): Promise<import('@/types/api').CameraConfig> {
  await ensureCsrfCookie();

  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cameras/${cameraId}`, {
    method: 'PATCH',
    headers: cookieAuthHeaders(),
    credentials: 'include',
    body: JSON.stringify(body),
  });

  return parseResponse(res);
}
