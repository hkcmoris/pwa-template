import { API_BASE } from '../utils/api';

const BASE =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.base) ||
    '';

export default function init(el: HTMLElement) {
    const form = el.querySelector('#login-form') as HTMLFormElement | null;
    const message = el.querySelector('#login-message');
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
                    window.location.href = `${BASE}/`;
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
