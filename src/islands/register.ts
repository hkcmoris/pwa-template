import { API_BASE } from '../utils/api';

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
