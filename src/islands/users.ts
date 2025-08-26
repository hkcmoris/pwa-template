export default async function init(el: HTMLElement) {
    const list = el.querySelector('#users-list');
    const message = el.querySelector('#users-message');
    try {
        const response = await fetch('/api/users.php', {
            credentials: 'include',
        });
        if (response.ok) {
            const data = await response.json();
            if (Array.isArray(data.users) && list) {
                list.innerHTML = data.users
                    .map(
                        (u: {
                            id: number;
                            email: string;
                            created_at: string;
                        }) => `<li>${u.email}</li>`
                    )
                    .join('');
            } else if (message) {
                message.textContent = 'No users found';
            }
        } else {
            const data = await response.json().catch(() => ({}));
            if (message) {
                message.textContent =
                    data.error || 'Failed to load users';
            }
        }
    } catch {
        if (message) {
            message.textContent = 'Failed to load users';
        }
    }
}

