'use client';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance: Echo<'pusher'> | null = null;

export function getEcho(token: string): Echo<'pusher'> {
  if (echoInstance) {
    return echoInstance;
  }

  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? 'trioseanse-local-key';
  const host = process.env.NEXT_PUBLIC_REVERB_HOST ?? '127.0.0.1';
  const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080);
  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';
  const forceTLS = scheme === 'https';

  window.Pusher = Pusher;

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key,
    // Pusher-js requires cluster even with custom wsHost; mt1 is a dummy for Reverb.
    cluster: 'mt1',
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${process.env.NEXT_PUBLIC_API_BASE_URL ?? 'http://localhost:8000'}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
  });

  return echoInstance;
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}
