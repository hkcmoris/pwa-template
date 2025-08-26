<div data-island="login">
    <h1>Login</h1>
    <form id="login-form" class="auth-form">
        <label class="auth-form__field">
            Email
            <input
                type="email"
                name="email"
                required
                autocomplete="username"
                class="auth-form__input"
            />
        </label>
        <label class="auth-form__field">
            Password
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="auth-form__input"
            />
        </label>
        <button type="submit">Login</button>
    </form>
    <div id="login-message"></div>
</div>
