export default function init() {
    const BASE =
        (typeof document !== 'undefined' &&
            document.documentElement?.dataset?.base) ||
        '';

    const navMenu = document.getElementById('editor-nav-menu');
    const navLinks = navMenu?.querySelectorAll<HTMLAnchorElement>('a');

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

    document.body.addEventListener('htmx:afterSwap', (e) => {
        const target =
            ((e as CustomEvent).detail &&
                ((e as CustomEvent).detail as { target?: HTMLElement })
                    .target) ||
            (e.target as HTMLElement);

        if (target?.id === 'content') {
            window.scrollTo(0, 0);
        }

        highlightNav();
    });

    highlightNav();
}
