import api from './api';
import { getAuthSession } from './auth';

const TOKEN_KEY = 'smartblood.device_token';
const TOKEN_REFRESH_MS = 30 * 60 * 1000;

let refreshHandle = null;

const createRandomToken = () => {
  if (window.crypto?.randomUUID) {
    return `web-${window.crypto.randomUUID()}`;
  }

  return `web-${Date.now()}-${Math.random().toString(36).slice(2)}`;
};

export const getOrCreateDeviceToken = () => {
  const existing = window.localStorage.getItem(TOKEN_KEY);

  if (existing) {
    return existing;
  }

  const token = createRandomToken();
  window.localStorage.setItem(TOKEN_KEY, token);

  return token;
};

export const registerDeviceToken = async () => {
  const session = getAuthSession();

  if (!session?.token) {
    return;
  }

  const token = getOrCreateDeviceToken();

  await api.post('/device-tokens/register', {
    token,
    platform: 'web',
  });
};

export const startDeviceTokenRefresh = () => {
  if (refreshHandle) {
    window.clearInterval(refreshHandle);
  }

  refreshHandle = window.setInterval(async () => {
    try {
      await registerDeviceToken();
    } catch (error) {
      // Silent retry cycle; network issues should not break the app.
    }
  }, TOKEN_REFRESH_MS);
};
