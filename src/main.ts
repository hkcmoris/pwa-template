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

window.addEventListener('popstate', () => {
    const path = window.location.pathname.replace(/^\/+/, '') || 'home';
    route = path;
    loadRoute(route);
});
