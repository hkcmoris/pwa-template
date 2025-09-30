<div data-island="register">
    <h1>Registrace</h1>
    <form id="register-form" class="auth-form">
        <label class="auth-form-field">
            Uživatelské jméno
            <input
                type="text"
                name="username"
                required
                autocomplete="username"
                class="auth-form-input"
            />
        </label>
        <label class="auth-form-field">
            E‑mail
            <input
                type="email"
                name="email"
                required
                autocomplete="email"
                class="auth-form-input"
            />
        </label>
        <label class="auth-form-field">
            Heslo
            <input
                type="password"
                name="password"
                required
                autocomplete="new-password"
                class="auth-form-input"
            />
        </label>
        <button type="submit">REGISTROVAT SE</button>
    </form>
    <div id="register-message"></div>
</div>
