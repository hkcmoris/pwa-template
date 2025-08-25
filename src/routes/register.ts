import { API_BASE } from '../utils/api';

export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Register</h1>
            <form id="register-form">
                <label>
                    Username
                    <input type="text" name="username" required autocomplete="username" />
                </label>
                <label>
                    Email
                    <input type="email" name="email" required autocomplete="email" />
                </label>
                <label>
                    Password
                    <input type="password" name="password" required autocomplete="new-password" />
                </label>
                <button type="submit">Register</button>
            </form>
            <div id="register-message"></div>
        `;
        const form = document.getElementById(
            'register-form'
        ) as HTMLFormElement | null;
        const message = document.getElementById('register-message');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            try {
                const response = await fetch(`${API_BASE}/register.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        username: formData.get('username'),
                        email: formData.get('email'),
                        password: formData.get('password'),
                    }),
                });

                let data: { token?: string; error?: string } = {};
                let errorText = '';
                try {
                    data = await response.json();
                } catch {
                    errorText = (await response.text().catch(() => '')).trim();
                }

                if (message) {
                    if (response.ok && data.token) {
                        message.textContent = 'Registration successful';
                        const email = formData.get('email') as string;
                        document.dispatchEvent(
                            new CustomEvent('auth-changed', { detail: email })
                        );
                    } else {
                        message.textContent =
                            data.error || errorText || 'Registration failed';
                    }
                }
            } catch {
                if (message) {
                    message.textContent = 'Registration failed';
                }
            }
        });
    }
}
