import { API_BASE } from './utils/api';

const pathRoute = window.location.pathname.replace(/^\/+/, '');
let route = pathRoute || document.body.dataset.route || 'home';

const loadRoute = async (name: string) => {
    const module = await import(`./routes/${name}.ts`);
    module.default?.();
};

const navigate = (name: string) => {
    route = name;
    loadRoute(route);
    history.pushState(null, '', `/${name}`);
};

const onIdle =
    (
        window as Window & {
            requestIdleCallback?: (cb: () => void) => number;
        }
    ).requestIdleCallback || ((cb: () => void) => setTimeout(cb, 0));

onIdle(() => loadRoute(route));

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

document.getElementById('login-btn')?.addEventListener('click', () => {
    navigate('login');
});

document.getElementById('register-btn')?.addEventListener('click', () => {
    navigate('register');
});

document.getElementById('users-btn')?.addEventListener('click', () => {
    navigate('users');
});

const usernameEl = document.getElementById('username');
const loginBtn = document.getElementById(
    'login-btn'
) as HTMLButtonElement | null;
const registerBtn = document.getElementById(
    'register-btn'
) as HTMLButtonElement | null;
const logoutBtn = document.getElementById(
    'logout-btn'
) as HTMLButtonElement | null;
const USER_KEY = 'userEmail';

const updateAuthUI = (email: string | null) => {
    if (usernameEl) {
        usernameEl.textContent = email || 'Guest';
    }
    if (loginBtn && registerBtn && logoutBtn) {
        if (email) {
            loginBtn.style.display = 'none';
            registerBtn.style.display = 'none';
            logoutBtn.style.display = '';
        } else {
            loginBtn.style.display = '';
            registerBtn.style.display = '';
            logoutBtn.style.display = 'none';
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
    navigate('home');
});

window.addEventListener('popstate', () => {
    const path = window.location.pathname.replace(/^\/+/, '') || 'home';
    route = path;
    loadRoute(route);
});
