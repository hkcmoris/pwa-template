import { API_BASE, getCsrfToken } from '../utils/api';

type RegisterResponse = {
    token?: string;
    error?: string;
    user?: {
        email?: string;
        role?: string;
    };
};

export default function init(el: HTMLElement) {
    const form = el.querySelector('#register-form') as HTMLFormElement | null;
    const message = el.querySelector('#register-message');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const csrfValue = formData.get('_csrf');
        const payload: Record<string, unknown> = {
            username: formData.get('username'),
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
            const response = await fetch(`${API_BASE}/register.php`, {
                method: 'POST',
                headers,
                credentials: 'include',
                body: JSON.stringify(payload),
            });

            let data: RegisterResponse = {};
            let errorText = '';
            try {
                data = await response.json();
            } catch {
                errorText = (await response.text().catch(() => '')).trim();
            }

            if (message) {
                if (response.ok && data.token) {
                    message.textContent = 'Registrace úspěšná';
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
                } else {
                    message.textContent =
                        data.error || errorText || 'Registrace se nezdařila';
                }
            }
        } catch {
            if (message) {
                message.textContent = 'Registrace se nezdařila';
            }
        }
    });
}
