import { API_BASE } from '../utils/api';

export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Register</h1>
            <form
                id="register-form"
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
                    Username
                    <input
                        type="text"
                        name="username"
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
                    Email
                    <input
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
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
                        autocomplete="new-password"
                        style="width: 100%;"
                    />
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
