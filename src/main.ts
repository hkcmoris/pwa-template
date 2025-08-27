import { API_BASE } from './utils/api';

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

const menuButton = document.getElementById('menu-toggle');
const navMenu = document.getElementById('nav-menu');

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

const applyTheme = (theme: string) => {
    document.documentElement.dataset.theme = theme;
};

const stored = localStorage.getItem(THEME_KEY);
if (stored) {
    applyTheme(stored);
}

themeToggle?.addEventListener('click', () => {
    const next =
        document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    localStorage.setItem(THEME_KEY, next);
});

const usernameEl = document.getElementById('username');
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
const aboutLink = document.getElementById(
    'about-link'
) as HTMLAnchorElement | null;
const USER_KEY = 'userEmail';

const updateAuthUI = (email: string | null) => {
    if (usernameEl) {
        usernameEl.textContent = email || 'Guest';
    }
    if (loginLink && registerLink && logoutBtn && usersLink) {
        if (email) {
            loginLink.classList.add('hidden');
            registerLink.classList.add('hidden');
            logoutBtn.classList.remove('hidden');
            usersLink.classList.remove('hidden');
        } else {
            loginLink.classList.remove('hidden');
            registerLink.classList.remove('hidden');
            logoutBtn.classList.add('hidden');
            usersLink.classList.add('hidden');
        }
    }
};

const storedUser = localStorage.getItem(USER_KEY);
updateAuthUI(storedUser);

document.addEventListener('auth-changed', (e) => {
    const email = (e as CustomEvent<string | null>).detail;
    if (email) {
        localStorage.setItem(USER_KEY, email);
    } else {
        localStorage.removeItem(USER_KEY);
    }
    updateAuthUI(email);
});

logoutBtn?.addEventListener('click', async () => {
    await fetch(`${API_BASE}/logout.php`, {
        method: 'POST',
        credentials: 'include',
    });
    document.dispatchEvent(new CustomEvent('auth-changed', { detail: null }));
    window.location.href = '/login';
});

document.body.addEventListener('htmx:afterSwap', (e) => {
    const target =
        ((e as CustomEvent).detail &&
            ((e as CustomEvent).detail as { target?: HTMLElement })
                .target) || (e.target as HTMLElement);

    if (target?.id === 'content') {
        window.scrollTo(0, 0);
    }

    mountIslands();
});
