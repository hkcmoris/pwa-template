import { API_BASE } from '../utils/api';

export default function init(el: HTMLElement) {
    const form = el.querySelector('#register-form') as HTMLFormElement | null;
    const message = el.querySelector('#register-message');
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
