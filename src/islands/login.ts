import { API_BASE, getCsrfToken } from '../utils/api';

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
        const csrfValue = formData.get('_csrf');
        const payload: Record<string, unknown> = {
            email: formData.get('email'),
            password: formData.get('password'),
        };
        if (typeof csrfValue === 'string' && csrfValue) {
            payload._csrf = csrfValue;
        }
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
        };
        const headerToken = getCsrfToken();
        if (headerToken) {
            headers['X-CSRF-Token'] = headerToken;
        }
        try {
            const response = await fetch(`${API_BASE}/login.php`, {
                method: 'POST',
                headers,
                credentials: 'include',
                body: JSON.stringify(payload),
            });
            const data = (await response
                .json()
                .catch(() => ({}))) as LoginResponse;
            if (message) {
                if (response.ok && data.token) {
                    message.textContent = 'Přihlášení úspěšné';
                    const emailValue =
                        (typeof data.user?.email === 'string' &&
                            data.user.email) ||
                        formData.get('email');
                    const email =
                        typeof emailValue === 'string' ? emailValue : '';
                    const role =
                        (typeof data.user?.role === 'string' &&
                            data.user.role) ||
                        'user';
                    document.dispatchEvent(
                        new CustomEvent('auth-changed', {
                            detail: { email, role },
                        })
                    );
                    window.location.href = `${BASE}/`;
                } else {
                    message.textContent =
                        data.error || 'Přihlášení se nezdařilo';
                }
            }
        } catch {
            if (message) {
                message.textContent = 'Přihlášení se nezdařilo';
            }
        }
    });
}
