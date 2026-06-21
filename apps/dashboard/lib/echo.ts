'use client';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { apiBaseUrl, readCookie } from '@/lib/api-client';

let echoInstance: Echo<'pusher'> | null = null;

export function getEcho(): Echo<'pusher'> {
  if (echoInstance) {
    return echoInstance;
  }

  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? 'trioseanse-local-key';
  const host = process.env.NEXT_PUBLIC_REVERB_HOST ?? '127.0.0.1';
  const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080);
  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';
  const forceTLS = scheme === 'https';
  const base = apiBaseUrl();

  window.Pusher = Pusher;

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key,
    cluster: 'mt1',
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${base}/broadcasting/auth`,
    auth: {
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
      },
    },
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: unknown) => void) => {
        fetch(`${base}/broadcasting/auth`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
          .then(async (response) => {
            if (!response.ok) {
              callback(new Error(`Broadcast auth failed: ${response.status}`), null);
              return;
            }
            callback(null, await response.json());
          })
          .catch((error: Error) => {
            callback(error, null);
          });
      },
    }),
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
