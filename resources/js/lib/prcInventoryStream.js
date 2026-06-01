import { getAuthSession } from './auth';

const buildStreamUrl = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  const token = getAuthSession()?.token;

  if (!token) {
    return null;
  }

  const url = new URL('/api/v1/prc/chapters/stream', window.location.origin);
  url.searchParams.set('token', token);
  return url.toString();
};

export const createInventoryStream = ({ onOpen, onUpdate, onError } = {}) => {
  const endpoint = buildStreamUrl();

  if (!endpoint || typeof EventSource === 'undefined') {
    onError?.(new Error('Real-time inventory updates are unavailable in this browser.'));
    return null;
  }

  const source = new EventSource(endpoint);

  source.onopen = () => {
    onOpen?.();
  };

  source.addEventListener('inventoryUpdate', (event) => {
    try {
      const payload = JSON.parse(event.data);
      onUpdate?.(payload.data ?? payload);
    } catch (error) {
      console.error('Error parsing inventory update event', error);
    }
  });

  source.addEventListener('message', (event) => {
    try {
      const payload = JSON.parse(event.data);
      onUpdate?.(payload.data ?? payload);
    } catch (error) {
      console.error('Error parsing inventory message event', error);
    }
  });

  source.onerror = (event) => {
    onError?.(event);
  };

  return source;
};
