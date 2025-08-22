export default async function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML =
            '<h1>Users</h1><ul id="users-list"></ul><div id="users-message"></div>';
        const list = document.getElementById('users-list');
        const message = document.getElementById('users-message');
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
}

