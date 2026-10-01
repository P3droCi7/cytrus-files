<?php
use App\Csrf;

use function App\e;

/** @var string $token */
/** @var bool $needsPassword */
/** @var ?string $error */
/** @var string $name */
/** @var string $size */
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($name) ?> - Cytrus Files</title>
<link rel="stylesheet" href="<?= e(\App\asset_version('assets/css/style.css')) ?>">
</head>
<body class="centered">
<div class="card">
    <h1>📁 Cytrus Files</h1>
    <p>Udostępniony plik: <strong><?= e($name) ?></strong> (<?= e($size) ?>)</p>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($needsPassword): ?>
        <form method="post" action="index.php?p=s&t=<?= e($token) ?>">
            <?= Csrf::field() ?>
            <label>Hasło do pliku <input type="password" name="password" required autofocus></label>
            <button type="submit">Pobierz</button>
        </form>
    <?php else: ?>
        <p><a href="index.php?p=s&t=<?= e($token) ?>">Kliknij, aby pobrać plik</a></p>
    <?php endif; ?>
</div>
</body>
</html>
