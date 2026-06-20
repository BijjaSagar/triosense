import type {
  ApiErrorResponse,
  ApiResponse,
  LocationState,
  LocationSummary,
  LoginResponse,
  PaginatedEvents,
} from '@/types/api';

const BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8000';

function authHeaders(token?: string): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  return headers;
}

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
  const res = await fetch(`${BASE}/api/v1/auth/login`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ email, password }),
  });

  return parseResponse<LoginResponse>(res);
}

export async function fetchLocations(token: string): Promise<LocationSummary[]> {
  const res = await fetch(`${BASE}/api/v1/locations`, {
    headers: authHeaders(token),
    cache: 'no-store',
  });

  const data = await parseResponse<{ locations: LocationSummary[] }>(res);
  return data.locations;
}

export async function fetchLocationState(
  locationId: number,
  token: string,
): Promise<LocationState> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/state`, {
    headers: authHeaders(token),
    cache: 'no-store',
  });

  return parseResponse<LocationState>(res);
}

export async function fetchLocationEvents(
  locationId: number,
  token: string,
  perPage = 50,
): Promise<PaginatedEvents> {
  const res = await fetch(
    `${BASE}/api/v1/locations/${locationId}/events?per_page=${perPage}`,
    {
      headers: authHeaders(token),
      cache: 'no-store',
    },
  );

  return parseResponse<PaginatedEvents>(res);
}

export async function fetchCutoffAccuracy(
  locationId: number,
  token: string,
): Promise<import('@/types/api').CutoffAccuracyReport> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cutoff-accuracy`, {
    headers: authHeaders(token),
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function fetchAnnouncements(
  locationId: number,
  token: string,
): Promise<{ items: import('@/types/api').AnnouncementItem[] }> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/announcements`, {
    headers: authHeaders(token),
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function applyCutoffOverride(
  locationId: number,
  token: string,
  body: { action: string; reason: string; cutoff_position?: number },
): Promise<LocationState> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cutoff/override`, {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });

  return parseResponse<LocationState>(res);
}

export async function updateLocation(
  locationId: number,
  token: string,
  body: { mode?: string; festival_mode?: boolean },
): Promise<{ location_id: number; mode: string; festival_mode: boolean }> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}`, {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });

  return parseResponse(res);
}

export async function fetchCrossCounterRecommendations(
  token: string,
): Promise<{ recommendations: import('@/types/api').CrossCounterRecommendation[] }> {
  const res = await fetch(`${BASE}/api/v1/cross-counter/recommendations`, {
    headers: authHeaders(token),
    cache: 'no-store',
  });

  return parseResponse(res);
}

export async function fetchLocationCameras(
  locationId: number,
  token: string,
): Promise<import('@/types/api').CameraConfig[]> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cameras`, {
    headers: authHeaders(token),
    cache: 'no-store',
  });

  const data = await parseResponse<{ cameras: import('@/types/api').CameraConfig[] }>(res);
  return data.cameras;
}

export async function updateLocationCamera(
  locationId: number,
  cameraId: number,
  token: string,
  body: {
    name?: string;
    role?: string;
    source_type?: string;
    rtsp_url?: string;
    tripwire_json?: import('@/types/api').TripwireConfig | null;
    status?: string;
  },
): Promise<import('@/types/api').CameraConfig> {
  const res = await fetch(`${BASE}/api/v1/locations/${locationId}/cameras/${cameraId}`, {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });

  return parseResponse(res);
}
