// Resolve API base at runtime for subfolder deployments (prefer same-origin)
const RUNTIME_BASE =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.base) ||
    '';
const ORIGIN = (typeof window !== 'undefined' && window.location?.origin) || '';

// Normalize to avoid accidental double slashes when appending paths
const RAW_API_BASE =
    (import.meta.env.VITE_API_BASE_URL as string | undefined) ||
    `${ORIGIN}${RUNTIME_BASE}/api`;

export const API_BASE = RAW_API_BASE.replace(/\/+$/, '');

type FetchInit = NonNullable<Parameters<typeof fetch>[1]>;

type FetchOptions = { skipRefresh?: boolean };

type HeadersBag = NonNullable<FetchInit['headers']>;

const CSRF_TOKEN =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.csrf) ||
    '';

export function getCsrfToken(): string {
    return CSRF_TOKEN;
}

export async function apiFetch(
    path: string,
    init?: FetchInit,
    options?: FetchOptions
) {
    const opts: FetchInit = {
        cache: 'no-store',
        credentials: 'include',
        ...(init || {}),
    };
    const method = (opts.method ?? 'GET').toString().toUpperCase();
    if (method !== 'GET' && method !== 'HEAD') {
        const token = getCsrfToken();
        if (token) {
            const headers = new Headers(opts.headers as HeadersBag | undefined);
            if (!headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', token);
            }
            opts.headers = headers;
        }
    }
    let res = await fetch(`${API_BASE}${path}`, opts);
    if (res.status === 401 && !options?.skipRefresh) {
        // Attempt to refresh access token
        const refreshToken = getCsrfToken();
        const refresh = await fetch(`${API_BASE}/refresh.php`, {
            method: 'POST',
            credentials: 'include',
            ...(refreshToken
                ? { headers: { 'X-CSRF-Token': refreshToken } }
                : {}),
        });
        if (refresh.ok) {
            res = await fetch(`${API_BASE}${path}`, opts);
        }
    }
    return res;
}
