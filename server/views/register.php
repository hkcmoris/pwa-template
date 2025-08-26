<div data-island="register">
    <h1>Register</h1>
    <form id="register-form" class="auth-form">
        <label class="auth-form__field">
            Username
            <input
                type="text"
                name="username"
                required
                autocomplete="username"
                class="auth-form__input"
            />
        </label>
        <label class="auth-form__field">
            Email
            <input
                type="email"
                name="email"
                required
                autocomplete="email"
                class="auth-form__input"
            />
        </label>
        <label class="auth-form__field">
            Password
            <input
                type="password"
                name="password"
                required
                autocomplete="new-password"
                class="auth-form__input"
            />
        </label>
        <button type="submit">Register</button>
    </form>
    <div id="register-message"></div>
</div>
