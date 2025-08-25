import { API_BASE } from '../utils/api';

export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Login</h1>
            <form
                id="login-form"
                style="
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 0.5rem;
                    max-width: 300px;
                    margin: 0 auto;
                "
            >
                <label
                    style="
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                    "
                >
                    Email
                    <input
                        type="email"
                        name="email"
                        required
                        autocomplete="username"
                        style="width: 100%;"
                    />
                </label>
                <label
                    style="
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                    "
                >
                    Password
                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        style="width: 100%;"
                    />
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
            try {
                const response = await fetch(`${API_BASE}/login.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        email: formData.get('email'),
                        password: formData.get('password'),
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (message) {
                    if (response.ok && data.token) {
                        message.textContent = 'Login successful';
                        const email = formData.get('email') as string;
                        document.dispatchEvent(
                            new CustomEvent('auth-changed', { detail: email })
                        );
                    } else {
                        message.textContent = data.error || 'Login failed';
                    }
                }
            } catch {
                if (message) {
                    message.textContent = 'Login failed';
                }
            }
        });
    }
}
