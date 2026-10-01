<?php
use App\Auth;
use App\Csrf;
use App\FileManager;

use function App\e;

/** @var array $entries */
/** @var string $currentDir */
/** @var array $breadcrumbs */
?>
<h1>Pliki</h1>

<nav class="breadcrumbs">
<?php foreach ($breadcrumbs as $i => $crumb): ?>
    <?php if ($i > 0): ?> / <?php endif; ?>
    <a href="index.php?<?= http_build_query(['p' => 'files', 'dir' => $crumb['path']]) ?>"><?= e($crumb['name']) ?></a>
<?php endforeach; ?>
</nav>

<?php if (Auth::can('upload')): ?>
<section class="panel">
    <h2>Prześlij pliki</h2>
    <form method="post" action="index.php?p=upload" enctype="multipart/form-data" id="uploadForm">
        <?= Csrf::field() ?>
        <input type="hidden" name="dir" value="<?= e($currentDir) ?>">
        <input type="file" name="files[]" multiple required>
        <button type="submit">Wyślij</button>
    </form>
    <div class="upload-progress" id="uploadProgress" hidden>
        <div class="upload-progress-bar" id="uploadProgressBar"></div>
        <span class="upload-progress-label" id="uploadProgressLabel">0%</span>
    </div>

    <h2>Nowy folder</h2>
    <form method="post" action="index.php?p=mkdir" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="dir" value="<?= e($currentDir) ?>">
        <input type="text" name="name" placeholder="Nazwa folderu" required maxlength="180">
        <button type="submit">Utwórz</button>
    </form>
</section>
<?php endif; ?>

<table class="file-table">
<thead>
<tr><th>Nazwa</th><th>Rozmiar</th><th>Modyfikacja</th><th>Akcje</th></tr>
</thead>
<tbody>
<?php if (empty($entries)): ?>
    <tr><td colspan="4">Ten folder jest pusty.</td></tr>
<?php endif; ?>
<?php foreach ($entries as $entry): ?>
    <tr>
        <td>
            <?php if ($entry['is_dir']): ?>
                📂 <a href="index.php?<?= http_build_query(['p' => 'files', 'dir' => $entry['relative']]) ?>"><?= e($entry['name']) ?></a>
            <?php else: ?>
                📄 <?= e($entry['name']) ?>
            <?php endif; ?>
        </td>
        <td><?= $entry['is_dir'] ? '-' : e(FileManager::humanSize($entry['size'])) ?></td>
        <td><?= e(date('Y-m-d H:i', $entry['mtime'])) ?></td>
        <td class="actions">
            <?php if (!$entry['is_dir']): ?>
                <a href="index.php?<?= http_build_query(['p' => 'download', 'path' => $entry['relative']]) ?>">Pobierz</a>
            <?php endif; ?>

            <?php if (Auth::can('upload')): ?>
                <details class="inline-details">
                    <summary>Zmień nazwę</summary>
                    <form method="post" action="index.php?p=rename" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="path" value="<?= e($entry['relative']) ?>">
                        <input type="text" name="new_name" value="<?= e($entry['name']) ?>" required maxlength="180">
                        <button type="submit">Zapisz</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (!$entry['is_dir'] && Auth::can('share')): ?>
                <details class="inline-details">
                    <summary>Udostępnij</summary>
                    <form method="post" action="index.php?p=share_create" class="inline-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="path" value="<?= e($entry['relative']) ?>">
                        <input type="password" name="password" placeholder="Hasło (opcjonalnie)">
                        <input type="number" name="expires_days" placeholder="Ważność (dni)" min="0">
                        <input type="number" name="max_downloads" placeholder="Limit pobrań" min="0">
                        <button type="submit">Utwórz link</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (Auth::can('delete')): ?>
                <form method="post" action="index.php?p=delete" class="inline-form" onsubmit="return confirm('Na pewno usunąć <?= e($entry['name']) ?>?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="path" value="<?= e($entry['relative']) ?>">
                    <button type="submit" class="danger">Usuń</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
