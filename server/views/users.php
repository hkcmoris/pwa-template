<?php

if (!isset($role) || !in_array($role, ['admin','superadmin'], true)) {
    echo '<h1>Přístup odepřen</h1><p>Nemáte oprávnění pro zobrazení uživatelů.</p>';
    return;
}
?>
<div data-island="users">
    <h1>Uživatelé</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Uživatelské jméno</th>
                <th>E‑mail</th>
                <th>Role</th>
                <th>Datum vytvoření</th>
            </tr>
        </thead>
        <tbody id="users-list"></tbody>
    </table>
    <div id="users-message"></div>
</div>
