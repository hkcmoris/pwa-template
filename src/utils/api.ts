// Resolve API base at runtime for subfolder deployments (prefer same-origin)
const RUNTIME_BASE =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.base) ||
    '';
const ORIGIN =
    (typeof window !== 'undefined' && window.location?.origin) ||
    '';

// Normalize to avoid accidental double slashes when appending paths
const RAW_API_BASE =
    (import.meta.env.VITE_API_BASE_URL as string | undefined) ||
    `${ORIGIN}${RUNTIME_BASE}/api`;

export const API_BASE = RAW_API_BASE.replace(/\/+$/, '');

type FetchInit = NonNullable<Parameters<typeof fetch>[1]>;

type FetchOptions = { skipRefresh?: boolean };

export async function apiFetch(
    path: string,
    init?: FetchInit,
    options?: FetchOptions
) {
    const opts: FetchInit = { credentials: 'include', ...(init || {}) };
    let res = await fetch(`${API_BASE}${path}`, opts);
    if (res.status === 401 && !options?.skipRefresh) {
        // Attempt to refresh access token
        const refresh = await fetch(`${API_BASE}/refresh.php`, {
            method: 'POST',
            credentials: 'include',
        });
        if (refresh.ok) {
            res = await fetch(`${API_BASE}${path}`, opts);
        }
    }
    return res;
}
