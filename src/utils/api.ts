// Resolve API base at runtime for subfolder deployments (prefer same-origin)
const RUNTIME_BASE =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.base) ||
    '';
const ORIGIN =
    (typeof window !== 'undefined' && window.location?.origin) ||
    '';

export const API_BASE =
    import.meta.env.VITE_API_BASE_URL || `${ORIGIN}${RUNTIME_BASE}/api`;

export async function apiFetch(path: string, init?: RequestInit) {
    const opts: RequestInit = { credentials: 'include', ...(init || {}) };
    let res = await fetch(`${API_BASE}${path}`, opts);
    if (res.status === 401) {
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
