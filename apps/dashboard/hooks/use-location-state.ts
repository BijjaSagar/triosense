'use client';

import { useEffect, useState } from 'react';
import { getEcho } from '@/lib/echo';
import { getToken } from '@/lib/auth';
import type { LocationState, LocationStateUpdatedEvent } from '@/types/api';

export function useLocationState(
  locationId: number,
  initial: LocationState,
): LocationState & { connectionState: 'connected' | 'connecting' | 'disconnected' } {
  const [state, setState] = useState<LocationState>(initial);
  const [connectionState, setConnectionState] = useState<
    'connected' | 'connecting' | 'disconnected'
  >('connecting');

  useEffect(() => {
    const token = getToken();
    if (!token) {
      setConnectionState('disconnected');
      return;
    }

    const echo = getEcho(token);
    setConnectionState('connecting');

    try {
      const channel = echo.private(`location.${locationId}`);

      channel.listen('.LocationStateUpdated', (event: LocationStateUpdatedEvent) => {
        setState((prev) => ({
          ...prev,
          as_of: event.as_of,
          tokens_remaining: event.tokens_remaining,
          queue_head: event.queue_head,
          queue_tail: event.queue_tail,
          cutoff_position: event.cutoff_position,
          status: event.status,
        }));
        setConnectionState('connected');
      });

      const connector = echo.connector?.pusher?.connection;
      if (!connector) {
        setConnectionState('disconnected');
        return;
      }

      const onConnected = () => setConnectionState('connected');
      const onDisconnected = () => setConnectionState('disconnected');
      const onConnecting = () => setConnectionState('connecting');

      connector.bind('connected', onConnected);
      connector.bind('disconnected', onDisconnected);
      connector.bind('connecting', onConnecting);

      if (connector.state === 'connected') {
        setConnectionState('connected');
      }

      return () => {
        connector.unbind('connected', onConnected);
        connector.unbind('disconnected', onDisconnected);
        connector.unbind('connecting', onConnecting);
        echo.leave(`location.${locationId}`);
      };
    } catch {
      setConnectionState('disconnected');
      return undefined;
    }
  }, [locationId]);

  return { ...state, connectionState };
}
