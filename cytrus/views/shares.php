<?php
use App\Controllers\FilesController;
use App\Csrf;

use function App\e;

/** @var array $shares */
/** @var bool $isAdmin */
?>
<h1>Udostępnione linki</h1>

<table class="file-table">
<thead>
<tr>
    <th>Plik</th>
    <?php if ($isAdmin): ?><th>Właściciel</th><?php endif; ?>
    <th>Link</th>
    <th>Hasło</th>
    <th>Wygasa</th>
    <th>Pobrania</th>
    <th>Status</th>
    <th>Akcje</th>
</tr>
</thead>
<tbody>
<?php if (empty($shares)): ?>
    <tr><td colspan="8">Brak udostępnionych linków.</td></tr>
<?php endif; ?>
<?php foreach ($shares as $share): ?>
    <tr>
        <td><?= e($share['path']) ?></td>
        <?php if ($isAdmin): ?><td><?= e($share['username'] ?? '-') ?></td><?php endif; ?>
        <td><code><?= e(FilesController::shareUrl($share['token'])) ?></code></td>
        <td><?= $share['password_hash'] ? 'Tak' : 'Nie' ?></td>
        <td><?= $share['expires_at'] ? e(date('Y-m-d H:i', (int) $share['expires_at'])) : 'Bezterminowo' ?></td>
        <td><?= (int) $share['downloads_count'] ?><?= $share['max_downloads'] ? ' / ' . (int) $share['max_downloads'] : '' ?></td>
        <td><?= $share['is_revoked'] ? 'Odwołany' : 'Aktywny' ?></td>
        <td>
            <?php if (!$share['is_revoked']): ?>
            <form method="post" action="index.php?p=share_revoke" class="inline-form" onsubmit="return confirm('Odwołać ten link?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $share['id'] ?>">
                <button type="submit" class="danger">Odwołaj</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
