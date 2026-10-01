<?php
use App\Csrf;

use function App\e;

/** @var array $users */
?>
<h1>Użytkownicy</h1>

<section class="panel">
    <h2>Nowy użytkownik</h2>
    <form method="post" action="index.php?p=admin_users_create" class="stacked-form">
        <?= Csrf::field() ?>
        <label>Login <input type="text" name="username" required pattern="[a-zA-Z0-9_.\-]{3,32}"></label>
        <label>Hasło <input type="password" name="password" required minlength="8"></label>
        <label>Rola
            <select name="role">
                <option value="user">Użytkownik</option>
                <option value="admin">Administrator</option>
            </select>
        </label>
        <label><input type="checkbox" name="can_upload" checked> może przesyłać pliki</label>
        <label><input type="checkbox" name="can_delete"> może usuwać pliki</label>
        <label><input type="checkbox" name="can_share" checked> może tworzyć linki do pobrania</label>
        <button type="submit">Utwórz</button>
    </form>
</section>

<table class="file-table">
<thead>
<tr><th>Login</th><th>Rola</th><th>Uprawnienia</th><th>Aktywny</th><th>Ostatnie logowanie</th><th>Akcje</th></tr>
</thead>
<tbody>
<?php foreach ($users as $u): ?>
    <tr>
        <td><?= e($u['username']) ?></td>
        <td><?= e($u['role']) ?></td>
        <td>
            <?= $u['can_upload'] ? 'przesyłanie ' : '' ?>
            <?= $u['can_delete'] ? 'usuwanie ' : '' ?>
            <?= $u['can_share'] ? 'udostępnianie' : '' ?>
        </td>
        <td><?= $u['is_active'] ? 'Tak' : 'Nie' ?></td>
        <td><?= $u['last_login_at'] ? e(date('Y-m-d H:i', (int) $u['last_login_at'])) : '-' ?></td>
        <td>
            <details class="inline-details">
                <summary>Edytuj</summary>
                <form method="post" action="index.php?p=admin_users_update" class="stacked-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <label>Rola
                        <select name="role">
                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Użytkownik</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </label>
                    <label><input type="checkbox" name="can_upload" <?= $u['can_upload'] ? 'checked' : '' ?>> może przesyłać</label>
                    <label><input type="checkbox" name="can_delete" <?= $u['can_delete'] ? 'checked' : '' ?>> może usuwać</label>
                    <label><input type="checkbox" name="can_share" <?= $u['can_share'] ? 'checked' : '' ?>> może udostępniać</label>
                    <label><input type="checkbox" name="is_active" <?= $u['is_active'] ? 'checked' : '' ?>> aktywny</label>
                    <label>Nowe hasło (opcjonalnie) <input type="password" name="new_password" minlength="8"></label>
                    <button type="submit">Zapisz</button>
                </form>
            </details>
            <form method="post" action="index.php?p=admin_users_delete" class="inline-form" onsubmit="return confirm('Usunąć użytkownika <?= e($u['username']) ?>?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="danger">Usuń</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
