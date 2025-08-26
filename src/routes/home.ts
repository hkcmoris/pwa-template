export default function init() {
    const content = document.getElementById('content');
    if (!content) return;

    const routes = Object.keys(import.meta.glob('./*.ts'))
        .map((path) => path.replace('./', '').replace('.ts', ''));

    const links = routes
        .map(
            (name) =>
                `<li><a data-route="${name}" href="/${name}">${name}</a></li>`
        )
        .join('');

    content.innerHTML = `
        <h1>Home</h1>
        <p>Welcome to the home page.</p>
        <ul>${links}</ul>
    `;
}
