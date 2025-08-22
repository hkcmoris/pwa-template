export default function init() {
    const content = document.getElementById('content');
    if (content) {
        content.innerHTML = `
            <h1>Register</h1>
            <form id="register-form">
                <label>
                    Username
                    <input type="text" name="username" required />
                </label>
                <label>
                    Email
                    <input type="email" name="email" required />
                </label>
                <label>
                    Password
                    <input type="password" name="password" required />
                </label>
                <button type="submit">Register</button>
            </form>
        `;
    }
}

