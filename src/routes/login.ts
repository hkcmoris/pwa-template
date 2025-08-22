export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Login</h1>
            <form id="login-form">
                <label>
                    Username
                    <input type="text" name="username" required />
                </label>
                <label>
                    Password
                    <input type="password" name="password" required />
                </label>
                <button type="submit">Login</button>
            </form>
        `;
    }
}

