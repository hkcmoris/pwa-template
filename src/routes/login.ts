export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Login</h1>
            <form id="login-form">
                <label>
                    Email
                    <input type="email" name="email" required />
                </label>
                <label>
                    Password
                    <input type="password" name="password" required />
                </label>
                <button type="submit">Login</button>
            </form>
            <div id="login-message"></div>
        `;
        const form = document.getElementById(
            'login-form'
        ) as HTMLFormElement | null;
        const message = document.getElementById('login-message');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const response = await fetch('/api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    email: formData.get('email'),
                    password: formData.get('password'),
                }),
            });
            if (message) {
                if (response.ok) {
                    message.textContent = 'Login successful';
                } else {
                    const data = await response.json().catch(() => ({}));
                    message.textContent = data.error || 'Login failed';
                }
            }
        });
    }
}

