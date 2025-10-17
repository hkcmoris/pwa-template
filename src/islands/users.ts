import { apiFetch } from '../utils/api';
import { enhanceSelects, setSelectValue } from './shared/select';

export default async function init(el: HTMLElement) {
    const list = el.querySelector<HTMLTableSectionElement>('#users-list');
    const message = el.querySelector<HTMLElement>('#users-message');
    const myRole = localStorage.getItem('userRole');
    const canEdit = myRole === 'superadmin';

    const myEmail = localStorage.getItem('userEmail');

    const render = (
        users: Array<{
            id: number;
            username: string;
            email: string;
            role?: string;
            created_at: string;
        }>
    ) => {
        if (!list) return;
        list.innerHTML = users
            .map((u) => {
                const role = u.role ?? 'user';
                const isSelf = canEdit && myEmail === u.email;
                const roleCell =
                    canEdit && !isSelf
                        ? `<div class="select" data-select data-user-id="${u.id}" data-prev="${role}">
                          <button type="button" class="select-button" aria-haspopup="listbox" aria-expanded="false">${role}</button>
                          <ul class="select-list" role="listbox" tabindex="-1" hidden>
                            <li role="option" class="select-option" data-value="user" aria-selected="${role === 'user'}">user</li>
                            <li role="option" class="select-option" data-value="admin" aria-selected="${role === 'admin'}">admin</li>
                            <li role="option" class="select-option" data-value="superadmin" aria-selected="${role === 'superadmin'}">superadmin</li>
                          </ul>
                       </div>`
                        : role +
                          (isSelf
                              ? ' <small title="Nelze měnit vlastní roli">(nelze změnit)</small>'
                              : '');
                return `<tr>
                    <td>${u.id}</td>
                    <td>${u.username}</td>
                    <td>${u.email}</td>
                    <td>${roleCell}</td>
                    <td>${new Date(u.created_at).toLocaleString()}</td>
                </tr>`;
            })
            .join('');

        if (canEdit) enhanceSelects(list);
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
                    list.addEventListener('select:change', async (ev) => {
                        const sel = (ev.target as HTMLElement).closest(
                            '.select[data-select]'
                        ) as HTMLElement | null;
                        if (!sel) return;
                        const userId = parseInt(sel.dataset.userId || '0', 10);
                        const prev = sel.getAttribute('data-prev') || '';
                        const next = (ev as CustomEvent).detail
                            ?.value as string;
                        if (!userId || !next || prev === next) return;
                        try {
                            const res = await apiFetch('/user-role.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    id: userId,
                                    role: next,
                                }),
                            });
                            if (!res.ok) {
                                setSelectValue(sel, prev);
                                let errorText = 'Aktualizace role se nezda�ila';
                                try {
                                    const payload: unknown = await res.json();
                                    if (
                                        payload &&
                                        typeof payload === 'object' &&
                                        'error' in payload
                                    ) {
                                        const maybe = (
                                            payload as { error?: unknown }
                                        ).error;
                                        if (
                                            typeof maybe === 'string' &&
                                            maybe.trim()
                                        ) {
                                            errorText = maybe;
                                        }
                                    }
                                } catch {
                                    // ignore JSON parse issues
                                }
                                if (message) {
                                    message.textContent = errorText;
                                }
                                return;
                            }
                            sel.setAttribute('data-prev', next);
                            if (message) message.textContent = 'Role uložena';
                        } catch {
                            setSelectValue(sel, prev);
                            if (message)
                                message.textContent =
                                    'Aktualizace role se nezdařila';
                        }
                    });
                }
            } else if (message) {
                message.textContent =
                    data.error || 'Žádní uživatelé nenalezeni';
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
