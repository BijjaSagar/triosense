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
