/**
 * Mercato Nova - client API leger.
 * Centralise toutes les requetes HTTP vers le backend PHP et la gestion CSRF.
 *
 * Usage :
 *   import { api } from './js/api.js';
 *   const { data } = await api.get('/artworks?sale_mode=enchere');
 */

const BASE = window.MN_API_BASE || 'http://localhost:8000/api';

let csrfToken = null;

async function ensureCsrf() {
  if (csrfToken) return csrfToken;
  // /auth/me renvoie aussi le token CSRF -> on l'utilise comme bootstrap.
  const res = await fetch(`${BASE}/auth/me`, { credentials: 'include' });
  const json = await res.json();
  csrfToken = json?.data?.csrf || null;
  return csrfToken;
}

async function request(method, path, body) {
  const headers = { 'Accept': 'application/json' };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (method !== 'GET' && method !== 'HEAD') {
    headers['X-CSRF-Token'] = await ensureCsrf();
  }

  const res = await fetch(`${BASE}${path}`, {
    method,
    credentials: 'include',
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  let json = null;
  try { json = await res.json(); } catch { /* reponse non-JSON */ }

  if (!res.ok || !json?.ok) {
    const err = new Error(json?.error || `HTTP ${res.status}`);
    err.status = res.status;
    err.fields = json?.fields || null;
    throw err;
  }
  return json;
}

export const api = {
  get:    (path)         => request('GET',    path),
  post:   (path, body)   => request('POST',   path, body ?? {}),
  put:    (path, body)   => request('PUT',    path, body ?? {}),
  delete: (path)         => request('DELETE', path),

  // Helpers metier
  me:       ()        => request('GET',  '/auth/me'),
  login:    (creds)   => request('POST', '/auth/login', creds),
  register: (creds)   => request('POST', '/auth/register', creds),
  logout:   ()        => request('POST', '/auth/logout', {}),

  artworks:      (params = {}) => {
    const q = new URLSearchParams(params).toString();
    return request('GET', `/artworks${q ? '?' + q : ''}`);
  },
  artwork:       (id) => request('GET', `/artworks/${id}`),
  categories:    ()   => request('GET', '/categories'),
};
