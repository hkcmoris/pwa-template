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

const initialCsrf =
    (typeof document !== 'undefined' &&
        (document.documentElement?.dataset?.csrf ||
            document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content') ||
            '')) ||
    '';

let csrfToken = initialCsrf;

let pendingCsrfRefresh: Promise<string | null> | null = null;

function syncDocumentCsrf(token: string) {
    if (typeof document === 'undefined') {
        return;
    }
    if (document.documentElement) {
        document.documentElement.dataset.csrf = token;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }
}

function updateCsrfToken(token: string) {
    csrfToken = token;
    if (token) {
        syncDocumentCsrf(token);
    }
}

async function fetchNewCsrfToken(): Promise<string | null> {
    try {
        const response = await fetch(`${API_BASE}/csrf.php`, {
            credentials: 'include',
            cache: 'no-store',
        });
        if (!response.ok) {
            return null;
        }
        const payload = (await response
            .json()
            .catch(() => null)) as { token?: string } | null;
        const token = payload?.token;
        if (typeof token === 'string' && token) {
            updateCsrfToken(token);
            return token;
        }
        return null;
    } catch {
        return null;
    }
}

async function ensureFreshCsrfToken(): Promise<string | null> {
    if (!pendingCsrfRefresh) {
        pendingCsrfRefresh = fetchNewCsrfToken();
        pendingCsrfRefresh.finally(() => {
            pendingCsrfRefresh = null;
        });
    }
    return pendingCsrfRefresh;
}

export function getCsrfToken(): string {
    return csrfToken;
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
    let headers: Headers | undefined;
    if (method !== 'GET' && method !== 'HEAD') {
        headers = new Headers(opts.headers as HeadersBag | undefined);
        const token = getCsrfToken();
        if (token) {
            headers.set('X-CSRF-Token', token);
        }
        opts.headers = headers;
    }

    const retryWithFreshCsrf = async (response: Response) => {
        const newToken = await ensureFreshCsrfToken();
        if (!newToken) {
            return response;
        }
        if (method !== 'GET' && method !== 'HEAD') {
            headers = headers ?? new Headers(opts.headers as HeadersBag | undefined);
            headers.set('X-CSRF-Token', newToken);
            opts.headers = headers;
        }
        return fetch(`${API_BASE}${path}`, opts);
    };

    let res = await fetch(`${API_BASE}${path}`, opts);
    if (res.status === 419) {
        res = await retryWithFreshCsrf(res);
    }
    if (res.status === 401 && !options?.skipRefresh) {
        // Attempt to refresh access token
        const refreshHeaders = new Headers();
        let refreshToken = getCsrfToken();
        if (!refreshToken) {
            refreshToken = (await ensureFreshCsrfToken()) ?? '';
        }
        if (refreshToken) {
            refreshHeaders.set('X-CSRF-Token', refreshToken);
        }
        const refreshOpts: FetchInit = {
            method: 'POST',
            credentials: 'include',
            headers: refreshHeaders,
        };
        let refresh = await fetch(`${API_BASE}/refresh.php`, refreshOpts);
        if (refresh.status === 419) {
            const newToken = (await ensureFreshCsrfToken()) ?? '';
            if (newToken) {
                refreshHeaders.set('X-CSRF-Token', newToken);
                refresh = await fetch(`${API_BASE}/refresh.php`, refreshOpts);
            }
        }
        if (refresh.ok) {
            res = await fetch(`${API_BASE}${path}`, opts);
            if (res.status === 419) {
                res = await retryWithFreshCsrf(res);
            }
        }
    }
    return res;
}
