import axios from 'axios';
import { clearAuthSession, getAuthSession } from './auth.js';

export const API_TIMEOUT_MS = 60000;

const api = axios.create({
  baseURL: '/api',
  timeout: API_TIMEOUT_MS,
  headers: {
    Accept: 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = getAuthSession()?.token;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401 && typeof window !== 'undefined') {
      clearAuthSession();

      if (window.location.pathname !== '/login') {
        window.location.assign('/login');
      }
    }

    return Promise.reject(error);
  }
);

export default api;
