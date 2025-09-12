import { apiFetch } from '../utils/api';

export default async function init(el: HTMLElement) {
    const list = el.querySelector<HTMLTableSectionElement>('#users-list');
    const message = el.querySelector<HTMLElement>('#users-message');
    const myRole = localStorage.getItem('userRole');
    const canEdit = myRole === 'superadmin';

    const myEmail = localStorage.getItem('userEmail');

    const render = (users: Array<{
        id: number;
        username: string;
        email: string;
        role?: string;
        created_at: string;
    }>) => {
        if (!list) return;
        list.innerHTML = users
            .map((u) => {
                const role = u.role ?? 'user';
                const isSelf = canEdit && myEmail === u.email;
                const roleCell = canEdit
                    ? `<select data-role-select data-user-id="${u.id}" data-prev="${role}"${
                          isSelf
                              ? ' disabled title="Nelze měnit vlastní roli"'
                              : ''
                      }>
                         <option value="user"${role === 'user' ? ' selected' : ''}>user</option>
                         <option value="admin"${role === 'admin' ? ' selected' : ''}>admin</option>
                         <option value="superadmin"${role === 'superadmin' ? ' selected' : ''}>superadmin</option>
                       </select>`
                    : role;
                return `<tr>
                    <td>${u.id}</td>
                    <td>${u.username}</td>
                    <td>${u.email}</td>
                    <td>${roleCell}</td>
                    <td>${new Date(u.created_at).toLocaleString()}</td>
                </tr>`;
            })
            .join('');
    };

    try {
        const response = await apiFetch('/users.php');
        if (response.ok) {
            const data = (await response.json()) as {
                users?: Array<{
                    id: number;
                    username: string;
                    email: string;
                    role?: string;
                    created_at: string;
                }>;
                error?: string;
            };
            if (Array.isArray(data.users)) {
                render(data.users);
                if (canEdit && list) {
                    list.addEventListener('change', async (e) => {
                        const sel = (e.target as HTMLElement).closest(
                            'select[data-role-select]'
                        ) as HTMLSelectElement | null;
                        if (!sel) return;
                        if (sel.disabled) return;
                        const userId = parseInt(sel.dataset.userId || '0', 10);
                        const prev = sel.getAttribute('data-prev') || '';
                        const next = sel.value;
                        if (!userId || prev === next) return;
                        try {
                            const res = await apiFetch(
                                '/user-role.php',
                                {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ id: userId, role: next }),
                                }
                            );
                            if (!res.ok) {
                                sel.value = prev;
                                const data = await res.json().catch(() => ({} as any));
                                if (message)
                                    message.textContent =
                                        data.error || 'Aktualizace role se nezdařila';
                                return;
                            }
                            sel.setAttribute('data-prev', next);
                            if (message) message.textContent = 'Role uložena';
                        } catch {
                            sel.value = prev;
                            if (message)
                                message.textContent = 'Aktualizace role se nezdařila';
                        }
                    });
                }
            } else if (message) {
                message.textContent = data.error || 'Žádní uživatelé nenalezeni';
            }
        } else {
            const data = await response.json().catch(() => ({}));
            if (message) {
                message.textContent = data.error || 'Načtení uživatelů se nezdařilo';
            }
        }
    } catch {
        if (message) {
            message.textContent = 'Načtení uživatelů se nezdařilo';
        }
    }
}

