import { apiFetch } from '../utils/api';

export default async function init(el: HTMLElement) {
    const list = el.querySelector<HTMLTableSectionElement>('#users-list');
    const message = el.querySelector('#users-message');
    try {
        const response = await apiFetch('/users.php');
        if (response.ok) {
            const data = await response.json();
            if (Array.isArray(data.users) && list) {
                list.innerHTML = data.users
                    .map(
                        (u: {
                            id: number;
                            username: string;
                            email: string;
                            created_at: string;
                        }) =>
                            `<tr><td>${u.id}</td><td>${u.username}</td><td>${u.email}</td><td>${new Date(
                                u.created_at
                            ).toLocaleString()}</td></tr>`
                    )
                    .join('');
            } else if (message) {
                message.textContent = 'Žádní uživatelé nenalezeni';
            }
        } else {
            const data = await response.json().catch(() => ({}));
            if (message) {
                message.textContent =
                    data.error || 'Načtení uživatelů se nezdařilo';
            }
        }
    } catch {
        if (message) {
            message.textContent = 'Načtení uživatelů se nezdařilo';
        }
    }
}

