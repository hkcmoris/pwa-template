<div data-island="login">
    <h1>Přihlášení</h1>
    <form id="login-form" class="auth-form">
        <?= csrf_field(); ?>
        <label class="auth-form-field">
            E‑mail
            <input
                type="email"
                name="email"
                required
                autocomplete="username"
                class="auth-form-input"
            />
        </label>
        <label class="auth-form-field">
            Heslo
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="auth-form-input"
            />
        </label>
        <button type="submit">PŘIHLÁSIT SE</button>
    </form>
    <div id="login-message"></div>
</div>
