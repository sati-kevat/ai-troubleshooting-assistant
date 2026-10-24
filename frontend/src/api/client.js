// Thin wrapper around fetch for the PHP REST API.
// The backend URL comes from VITE_API_BASE_URL; no secrets live in the frontend.

export const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/pinelabs-api/api').replace(/\/$/, '');

// Longer than the backend's AI_TIMEOUT_SECONDS (120s default for a local
// Ollama model) so a slow AI call ends in the backend's template fallback,
// not a browser timeout.
const REQUEST_TIMEOUT_MS = 150000;

export class ApiError extends Error {
  constructor(message, { status = 0, code = 'network_error', fields = null } = {}) {
    super(message);
    this.status = status;
    this.code = code;
    this.fields = fields;
  }
}

async function request(path, { method = 'GET', body, signal } = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(new DOMException('Timeout', 'TimeoutError')), REQUEST_TIMEOUT_MS);
  if (signal) {
    signal.addEventListener('abort', () => controller.abort(signal.reason), { once: true });
  }

  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers: body ? { 'Content-Type': 'application/json' } : undefined,
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (error) {
    if (signal?.aborted) throw error;
    if (error?.name === 'TimeoutError' || controller.signal.reason?.name === 'TimeoutError') {
      throw new ApiError('The server took too long to respond. Please try again.', { code: 'timeout' });
    }
    throw new ApiError(
      'Cannot reach the backend API. Check that Apache and MySQL are running in the XAMPP Control Panel.',
      { code: 'network_error' },
    );
  } finally {
    clearTimeout(timeout);
  }

  let data = null;
  try {
    data = await response.json();
  } catch {
    // Non-JSON response (e.g. an Apache error page) is handled below.
  }

  if (!response.ok) {
    const error = data?.error;
    throw new ApiError(error?.message || `Request failed (HTTP ${response.status}).`, {
      status: response.status,
      code: error?.code || 'http_error',
      fields: error?.fields || null,
    });
  }
  if (data === null) {
    throw new ApiError('The server returned an unexpected response.', { status: response.status, code: 'invalid_response' });
  }
  return data;
}

function queryString(params) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
  });
  const text = search.toString();
  return text ? `?${text}` : '';
}

export const api = {
  health: (options) => request('/health', options),
  categories: (options) => request('/categories', options),
  articles: (params = {}, options) => request(`/articles${queryString(params)}`, options),
  article: (code, options) => request(`/articles/${encodeURIComponent(code)}`, options),
  troubleshoot: (payload) => request('/troubleshoot', { method: 'POST', body: payload }),
  queries: (params = {}, options) => request(`/queries${queryString(params)}`, options),
  query: (id, options) => request(`/queries/${encodeURIComponent(id)}`, options),
  updateQueryStatus: (id, payload) => request(`/queries/${encodeURIComponent(id)}`, { method: 'PATCH', body: payload }),
  analytics: (params = {}, options) => request(`/analytics${queryString(params)}`, options),
};
