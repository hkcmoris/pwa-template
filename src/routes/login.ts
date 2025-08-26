import { API_BASE } from '../utils/api';
import './auth-form.css';

export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Login</h1>
            <form id="login-form" class="auth-form">
                <label class="auth-form__field">
                    Email
                    <input
                        type="email"
                        name="email"
                        required
                        autocomplete="username"
                        class="auth-form__input"
                    />
                </label>
                <label class="auth-form__field">
                    Password
                    <input
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="auth-form__input"
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
