const BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8000';

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

export async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${BASE}/sanctum/csrf-cookie`, {
    method: 'GET',
    credentials: 'include',
  });
}

export function apiBaseUrl(): string {
  return BASE;
}

export function defaultHeaders(): HeadersInit {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };

  const xsrf = readCookie('XSRF-TOKEN');
  if (xsrf) {
    headers['X-XSRF-TOKEN'] = xsrf;
  }

  return headers;
}

export function cookieAuthHeaders(): HeadersInit {
  return defaultHeaders();
}

export { readCookie };
