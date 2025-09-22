import { API_BASE } from '../utils/api';

const BASE =
    (typeof document !== 'undefined' &&
        document.documentElement?.dataset?.base) ||
    '';

type LoginResponse = {
    token?: string;
    error?: string;
    user?: {
        email?: string;
        role?: string;
    };
};

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
            const data = (await response.json().catch(() => ({}))) as LoginResponse;
            if (message) {
                if (response.ok && data.token) {
                    message.textContent = 'Přihlášení úspěšné';
                    const emailValue =
                        (typeof data.user?.email === 'string' && data.user.email) ||
                        formData.get('email');
                    const email =
                        typeof emailValue === 'string' ? emailValue : '';
                    const role =
                        (typeof data.user?.role === 'string' && data.user.role) ||
                        'user';
                    document.dispatchEvent(
                        new CustomEvent('auth-changed', { detail: { email, role } })
                    );
                    window.location.href = `${BASE}/`;
                } else {
                    message.textContent = data.error || 'Přihlášení se nezdařilo';
                }
            }
        } catch {
            if (message) {
                message.textContent = 'Přihlášení se nezdařilo';
            }
        }
    });
}
