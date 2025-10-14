import { API_BASE, apiFetch, getCsrfToken } from './utils/api';

const HOME_BG_LIGHT = new URL('./assets/bg-light.webp', import.meta.url).href;
const HOME_BG_DARK = new URL('./assets/bg-dark.webp', import.meta.url).href;

if (typeof document !== 'undefined') {
    document.documentElement.style.setProperty(
        '--home-bg-light',
        `url("${HOME_BG_LIGHT}")`
    );
    document.documentElement.style.setProperty(
        '--home-bg-dark',
        `url("${HOME_BG_DARK}")`
    );
}


const normalizeRoute = (value: string) => value.replace(/^\/+|\/+$/g, '');
const getCurrentRoute = () => {
    const base = BASE || '';
    const url = new URL(window.location.href);
    let pathname = url.pathname;
    if (base && pathname.startsWith(base)) {
        pathname = pathname.slice(base.length);
    }
    const fallback = normalizeRoute(pathname);
    const routeParam = url.searchParams.get('r');
    return normalizeRoute(routeParam ?? fallback);
};

const updateBodyRoute = () => {
    if (!document.body) {
        return;
    }
    const route = getCurrentRoute();
    document.body.dataset.route = route === '' ? 'home' : route;
};

const BASE =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.base) ||
    '';

const onIdle =
    (
        window as Window & {
            requestIdleCallback?: (cb: () => void) => number;
        }
    ).requestIdleCallback || ((cb: () => void) => setTimeout(cb, 0));

const islandModules = import.meta.glob('./islands/*.ts');

const mountIslands = (root: Document | HTMLElement = document) => {
    root.querySelectorAll<HTMLElement>(
        '[data-island]:not([data-island-mounted])'
    ).forEach(async (el) => {
        const name = el.dataset.island!;
        const loader = islandModules[`./islands/${name}.ts`];
        if (loader) {
            const module = (await loader()) as {
                default?: (el: HTMLElement) => void;
            };
            module.default?.(el);
            el.setAttribute('data-island-mounted', '');
        }
    });
};

onIdle(() => mountIslands());

// In dev, lazy-load font CSS from Vite server after first paint
if (import.meta.env.DEV) {
    onIdle(() => import('/src/styles/fonts.css'));
}

const menuButton = document.getElementById('menu-toggle');
const navMenu = document.getElementById('nav-menu');
const navLinks = navMenu?.querySelectorAll<HTMLAnchorElement>('a');

const highlightNav = () => {
    const base = BASE || '';
    const current = getCurrentRoute();
    navLinks?.forEach((link) => {
        const url = new URL(link.href, window.location.href);
        let path = url.pathname;
        if (base && path.startsWith(base)) {
            path = path.slice(base.length);
        }
        const fallback = normalizeRoute(path);
        const route = normalizeRoute(url.searchParams.get('r') ?? fallback);
        const match = normalizeRoute(link.dataset.activeRoot || route);
        const isHome = match === '';
        const active = isHome
            ? current === ''
            : current === match || current.startsWith(`${match}/`);
        link.classList.toggle('active', active);
    });
};

highlightNav();
updateBodyRoute();

menuButton?.addEventListener('click', () => {
    navMenu?.classList.toggle('open');
});

navMenu?.addEventListener('click', (e) => {
    const target = (e.target as HTMLElement).closest('a,button');
    if (navMenu.classList.contains('open') && target) {
        navMenu.classList.remove('open');
    }
});

const themeToggle = document.getElementById('theme-toggle');
const THEME_KEY = 'theme';

const setThemeCookie = (theme: string) => {
    const path = BASE || '/';
    document.cookie = `theme=${theme};path=${path};max-age=31536000;SameSite=Lax`;
};

const applyTheme = (theme: string) => {
    document.documentElement.dataset.theme = theme;
    setThemeCookie(theme);
};

const getCookie = (name: string) =>
    document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')[1];

const stored = localStorage.getItem(THEME_KEY) ?? getCookie(THEME_KEY);
if (stored) {
    applyTheme(stored);
}

themeToggle?.addEventListener('click', () => {
    const next =
        document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem(THEME_KEY, next);
});

const usernameEl = document.getElementById('username-right');
const editorLink = document.getElementById(
    'editor-link'
) as HTMLAnchorElement | null;
const loginLink = document.getElementById(
    'login-link'
) as HTMLAnchorElement | null;
const registerLink = document.getElementById(
    'register-link'
) as HTMLAnchorElement | null;
const logoutBtn = document.getElementById(
    'logout-btn'
) as HTMLButtonElement | null;
const usersLink = document.getElementById(
    'users-link'
) as HTMLAnchorElement | null;
const configuratorLink = document.getElementById(
    'configurator-link'
) as HTMLAnchorElement | null;
const USER_KEY = 'userEmail';
const ROLE_KEY = 'userRole';

type AuthChangedDetail = {
    email: string;
    role?: string;
} | null;

type HtmxConfigDetail = {
    path?: string;
    parameters?: Record<string, string> | null;
};

const REFRESH_INTERVAL_MS = 8 * 60 * 1000;
const REFRESH_RETRY_MS = 60 * 1000;
let refreshTimer: ReturnType<typeof setTimeout> | undefined;
let authEpoch = 0;

const stopTokenRefresh = () => {
    if (refreshTimer !== undefined) {
        clearTimeout(refreshTimer);
        refreshTimer = undefined;
    }
};

const scheduleTokenRefresh = (delay = REFRESH_INTERVAL_MS) => {
    stopTokenRefresh();
    if (!localStorage.getItem(USER_KEY)) {
        return;
    }
    const scheduledEpoch = authEpoch;
    refreshTimer = setTimeout(async () => {
        if (scheduledEpoch !== authEpoch) {
            return;
        }
        if (!localStorage.getItem(USER_KEY)) {
            stopTokenRefresh();
            return;
        }
        try {
            const res = await apiFetch('/me.php');
            if (scheduledEpoch !== authEpoch) {
                return;
            }
            if (res.ok) {
                const payload = (await res.json().catch(() => null)) as {
                    user?: { email: string; role: string } | null;
                } | null;
                const user = payload?.user ?? null;
                if (scheduledEpoch !== authEpoch) {
                    return;
                }
                if (user) {
                    updateAuthUI(user.email);
                    localStorage.setItem(USER_KEY, user.email);
                    localStorage.setItem(ROLE_KEY, user.role);
                    setRoleUI(user.role);
                    scheduleTokenRefresh(REFRESH_INTERVAL_MS);
                    return;
                }
            } else if (res.status === 401) {
                clearStoredAuth();
                applyLoggedOutUI();
                return;
            }
        } catch {
            // swallow network errors and retry soon
        }
        if (scheduledEpoch !== authEpoch) {
            return;
        }
        if (localStorage.getItem(USER_KEY)) {
            scheduleTokenRefresh(REFRESH_RETRY_MS);
        } else {
            stopTokenRefresh();
        }
    }, delay);
};
const updateAuthUI = (email: string | null) => {
    if (usernameEl) {
        usernameEl.textContent = email || 'Návštěvník';
    }
    if (loginLink && registerLink && logoutBtn) {
        if (email) {
            loginLink.classList.add('hidden');
            registerLink.classList.add('hidden');
            logoutBtn.classList.remove('hidden');
            configuratorLink?.classList.remove('hidden');
        } else {
            loginLink.classList.remove('hidden');
            registerLink.classList.remove('hidden');
            logoutBtn.classList.add('hidden');
            configuratorLink?.classList.add('hidden');
        }
    }
};

const setRoleUI = (role: string | null) => {
    const allowed = role === 'admin' || role === 'superadmin';
    if (editorLink) {
        editorLink.classList.toggle('hidden', !allowed);
    }
    if (usersLink) {
        usersLink.classList.toggle('hidden', !allowed);
    }
};

const clearStoredAuth = () => {
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem(ROLE_KEY);
};

const applyLoggedOutUI = () => {
    updateAuthUI(null);
    setRoleUI(null);
    stopTokenRefresh();
};

const FETCH_RETRY_MS = 500;

async function fetchMeAndUpdate(
    expectedEpoch: number = authEpoch,
    options: { preventDowngrade?: boolean } = {}
) {
    const { preventDowngrade = false } = options;
    try {
        // Allow refresh on 401 so links don't disappear when access expires.
        const res = await apiFetch('/me.php');
        if (expectedEpoch !== authEpoch) {
            return;
        }
        if (!res.ok) {
            if (preventDowngrade) {
                setTimeout(() => fetchMeAndUpdate(authEpoch), FETCH_RETRY_MS);
            } else {
                clearStoredAuth();
                applyLoggedOutUI();
            }
            return;
        }
        const data = (await res.json()) as {
            user: { email: string; role: string } | null;
        };
        if (expectedEpoch !== authEpoch) {
            return;
        }
        if (data.user) {
            updateAuthUI(data.user.email);
            localStorage.setItem(USER_KEY, data.user.email);
            localStorage.setItem(ROLE_KEY, data.user.role);
            setRoleUI(data.user.role);
            scheduleTokenRefresh();
        } else if (preventDowngrade) {
            setTimeout(() => fetchMeAndUpdate(authEpoch), FETCH_RETRY_MS);
        } else {
            clearStoredAuth();
            applyLoggedOutUI();
        }
    } catch {
        if (expectedEpoch !== authEpoch) {
            return;
        }
        if (preventDowngrade) {
            setTimeout(() => fetchMeAndUpdate(authEpoch), FETCH_RETRY_MS);
            return;
        }
        clearStoredAuth();
        applyLoggedOutUI();
    }
}

const storedUser = localStorage.getItem(USER_KEY);
updateAuthUI(storedUser);
// Also restore role-based UI from storage (best effort) and then refresh via API
setRoleUI(localStorage.getItem(ROLE_KEY));
const initialEpoch = authEpoch;
onIdle(() =>
    fetchMeAndUpdate(initialEpoch, { preventDowngrade: Boolean(storedUser) })
);

document.addEventListener('auth-changed', (event) => {
    authEpoch += 1;
    const currentEpoch = authEpoch;
    const detail = (event as CustomEvent<AuthChangedDetail>).detail;
    if (detail && detail.email) {
        localStorage.setItem(USER_KEY, detail.email);
        if (detail.role) {
            localStorage.setItem(ROLE_KEY, detail.role);
        }
        scheduleTokenRefresh();
    } else {
        clearStoredAuth();
        stopTokenRefresh();
    }
    updateAuthUI(detail?.email ?? null);
    const roleHint = detail
        ? (detail.role ?? localStorage.getItem(ROLE_KEY))
        : null;
    setRoleUI(roleHint);
    onIdle(() =>
        fetchMeAndUpdate(currentEpoch, {
            preventDowngrade: Boolean(detail?.email),
        })
    );
});

logoutBtn?.addEventListener('click', async () => {
    stopTokenRefresh();
    const csrfToken = getCsrfToken();
    await fetch(`${API_BASE}/logout.php`, {
        method: 'POST',
        credentials: 'include',
        ...(csrfToken ? { headers: { 'X-CSRF-Token': csrfToken } } : {}),
    });
    document.dispatchEvent(new CustomEvent('auth-changed', { detail: null }));
    const pretty = (document.documentElement.dataset.pretty ?? '1') !== '0';
    window.location.href = pretty ? `${BASE}/login` : `${BASE}/?r=login`;
});

document.body.addEventListener('htmx:historyRestore', () => {
    highlightNav();
    updateBodyRoute();
});

const ensureEditorStyleSlot = () => {
    if (typeof document === 'undefined') {
        return;
    }
    const head = document.head;
    if (!head || document.getElementById('editor-partial-style')) {
        return;
    }
    const link = document.createElement('link');
    link.id = 'editor-partial-style';
    link.rel = 'stylesheet';
    head.appendChild(link);
};

const cloneLinkElement = (source: HTMLLinkElement) => {
    const clone = document.createElement('link');
    clone.rel = source.rel || 'stylesheet';
    const href = source.getAttribute('href');
    if (href) {
        clone.href = href;
    }
    if (source.media) {
        clone.media = source.media;
    }
    const copyAttr = (name: string) => {
        const value = source.getAttribute(name);
        if (value) {
            clone.setAttribute(name, value);
        }
    };
    copyAttr('crossorigin');
    copyAttr('integrity');
    copyAttr('referrerpolicy');
    copyAttr('title');
    return clone;
};

let latestEditorStylesheetHref: string | null = null;

const swapEditorStylesheet = (incoming: HTMLLinkElement) => {
    if (typeof document === 'undefined') {
        return;
    }
    const head = document.head;
    if (!head) {
        return;
    }
    const hrefAttr = incoming.getAttribute('href');
    if (!hrefAttr) {
        return;
    }

    const existing = document.getElementById(
        incoming.id || 'editor-partial-style'
    ) as HTMLLinkElement | null;

    if (!existing) {
        const fresh = cloneLinkElement(incoming);
        fresh.id = incoming.id || 'editor-partial-style';
        latestEditorStylesheetHref = hrefAttr;
        head.appendChild(fresh);
        return;
    }

    const currentHref = existing.getAttribute('href');
    if (currentHref === hrefAttr) {
        return;
    }

    const next = cloneLinkElement(incoming);
    const slotId = existing.id || incoming.id || 'editor-partial-style';
    const media = incoming.media || 'all';

    next.media = 'print';
    latestEditorStylesheetHref = hrefAttr;

    next.addEventListener('load', () => {
        if (latestEditorStylesheetHref !== hrefAttr) {
            next.remove();
            return;
        }
        next.media = media;
        const active = document.getElementById(slotId);
        if (active && active !== next) {
            active.remove();
        }
        next.id = slotId;
    });

    next.addEventListener('error', () => {
        next.remove();
    });

    head.appendChild(next);
};

document.body?.addEventListener('htmx:configRequest', (event) => {
    const detail = (event as CustomEvent<HtmxConfigDetail>).detail;
    const path = (detail?.path || '').toLowerCase();
    const queryRoute = (detail?.parameters?.r || '').toLowerCase();

    if (path.includes('/editor/') || queryRoute.startsWith('editor')) {
        ensureEditorStyleSlot();
    }
});

document.body.addEventListener('htmx:oobBeforeSwap', (event) => {
    const detail = (event as CustomEvent).detail as
        | { shouldSwap?: boolean; content?: Element | null }
        | undefined;
    const candidate = detail?.content;
    if (candidate instanceof HTMLLinkElement) {
        if (candidate.id === 'editor-partial-style') {
            if (detail) {
                detail.shouldSwap = false;
            }
            swapEditorStylesheet(candidate);
        }
    }
});

document.body.addEventListener('htmx:afterSwap', (e) => {
    const target =
        ((e as CustomEvent).detail &&
            ((e as CustomEvent).detail as { target?: HTMLElement }).target) ||
        (e.target as HTMLElement);

    if (target?.id === 'content') {
        window.scrollTo(0, 0);
    }

    mountIslands();
    highlightNav();
    updateBodyRoute();
});

document.body.addEventListener('htmx:oobAfterSwap', (event) => {
    const detail = (event as CustomEvent).detail as
        | { content?: Element }
        | undefined;
    const swapped = detail?.content ?? (event.target as Element | null);
    if (swapped instanceof HTMLElement) {
        mountIslands(swapped as HTMLElement);
    } else {
        mountIslands();
    }
});

