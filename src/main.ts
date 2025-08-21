const pathRoute = window.location.pathname.replace(/^\/+/, '');
const route = pathRoute || document.body.dataset.route || 'home';

const loadRoute = async () => {
    const module = await import(`./routes/${route}.ts`);
    module.default?.();
};

const onIdle =
    (
        window as Window & {
            requestIdleCallback?: (cb: () => void) => number;
        }
    ).requestIdleCallback || ((cb: () => void) => setTimeout(cb, 0));

onIdle(loadRoute);

const menuButton = document.getElementById('menu-toggle');
const navMenu = document.getElementById('nav-menu');

menuButton?.addEventListener('click', () => {
    navMenu?.classList.toggle('open');
});

const themeToggle = document.getElementById('theme-toggle');

themeToggle?.addEventListener('click', () => {
    const next =
        document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
});
