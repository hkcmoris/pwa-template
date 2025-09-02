<div data-island="login">
    <h1>Přihlášení</h1>
    <form id="login-form" class="auth-form">
        <label class="auth-form__field">
            E‑mail
            <input
                type="email"
                name="email"
                required
                autocomplete="username"
                class="auth-form__input"
            />
        </label>
        <label class="auth-form__field">
            Heslo
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="auth-form__input"
            />
        </label>
        <button type="submit">Přihlásit se</button>
    </form>
    <div id="login-message"></div>
</div>
