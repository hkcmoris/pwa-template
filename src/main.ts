const route = document.body.dataset.route;

if (route) {
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
}
