import i18n from '../i18n.js';

const API = (import.meta.env.VITE_API_URL || '/backend').replace(/\/+$/, '');
const TOKEN_KEYS = ['access_token', 'token', 'jwt'];

export class ApiError extends Error {
  constructor(message, status, data) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
  }
}

function getStoredToken() {
  try {
    for (const key of TOKEN_KEYS) {
      const token = window.localStorage.getItem(key);
      if (token) return token;
    }
  } catch {
    // Storage may be unavailable in restricted browser contexts.
  }
  return null;
}

function clearStoredTokens() {
  try {
    TOKEN_KEYS.forEach(key => window.localStorage.removeItem(key));
  } catch {
    // Session-cookie authentication can still be cleared by the server.
  }
}

function handleUnauthorized() {
  clearStoredTokens();
  window.dispatchEvent(new Event('hrms:unauthorized'));
  if (window.location.hash !== '#login') window.location.hash = '#login';
}

async function wait(delay, retry) {
  await new Promise(resolve => window.setTimeout(resolve, delay * (2 ** retry)));
}

function errorMessage(response, data) {
  if (data?.translation_key) return i18n.t(data.translation_key);
  if (data?.error || data?.message) return data.error || data.message;
  if (response.status >= 500) return i18n.t('apiServerError', { status: response.status });
  if (response.status === 403) return 'You do not have permission to perform this action.';
  return i18n.t('apiResponseError', { status: response.status });
}

export async function apiClient(endpoint, options = {}, retries = 2, delay = 500) {
  const headers = new Headers(options.headers || {});
  if (!headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
  const token = getStoredToken();
  if (token && !headers.has('Authorization')) headers.set('Authorization', `Bearer ${token}`);

  const url = `${API}/${String(endpoint).replace(/^\/+/, '')}`;
  let response;
  for (let attempt = 0; ; attempt += 1) {
    try {
      response = await fetch(url, { ...options, headers, credentials: 'include' });
    } catch (error) {
      if (error.name === 'AbortError') throw error;
      if (attempt < retries) {
        await wait(delay, attempt);
        continue;
      }
      throw new ApiError(i18n.t('apiConnectionError'));
    }

    if ([502, 503, 504].includes(response.status) && attempt < retries) {
      await wait(delay, attempt);
      continue;
    }
    break;
  }

  let text;
  try {
    text = await response.text();
  } catch {
    throw new ApiError(i18n.t('apiConnectionError'), response.status);
  }

  if (response.status === 401) handleUnauthorized();

  let data = {};
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      const message = response.status >= 500
        ? i18n.t('apiServerError', { status: response.status })
        : i18n.t('apiResponseError', { status: response.status });
      throw new ApiError(message, response.status);
    }
  }

  if (!response.ok) {
    throw new ApiError(errorMessage(response, data), response.status, data);
  }

  return data;
}