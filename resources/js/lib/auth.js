const SESSION_KEY = 'smartblood.auth';

export const getAuthSession = () => {
  try {
    const raw = window.localStorage.getItem(SESSION_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (error) {
    return null;
  }
};

export const setAuthSession = (payload) => {
  const user = payload?.user || null;
  const token = payload?.token || null;

  const session = {
    token,
    user,
    updatedAt: new Date().toISOString(),
  };

  window.localStorage.setItem(SESSION_KEY, JSON.stringify(session));
  return session;
};

export const clearAuthSession = () => {
  window.localStorage.removeItem(SESSION_KEY);
};

export const logoutSession = async () => {
  try {
    await fetch('/api/logout', {
      method: 'POST',
      headers: authHeaders({
        'Content-Type': 'application/json',
      }),
    });
  } catch (error) {
    // Ignore network errors and still clear local session.
  }

  clearAuthSession();
};

export const authHeaders = (extraHeaders = {}) => {
  const token = getAuthSession()?.token;

  return {
    Accept: 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extraHeaders,
  };
};

export const isAuthenticated = () => Boolean(getAuthSession()?.token);

export const currentUser = () => getAuthSession()?.user || null;
