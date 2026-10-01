<?php
use App\Csrf;

use function App\e;
use function App\get_flashes;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pierwsza konfiguracja - Cytrus Files</title>
<link rel="stylesheet" href="<?= e(\App\asset_version('assets/css/style.css')) ?>">
</head>
<body class="centered">
<div class="card">
    <h1>📁 Cytrus Files</h1>
    <p>Utwórz pierwsze konto administratora, aby rozpocząć korzystanie z aplikacji.</p>
    <?php foreach (get_flashes() as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="index.php?p=setup">
        <?= Csrf::field() ?>
        <label>Login administratora
            <input type="text" name="username" required autofocus pattern="[a-zA-Z0-9_.\-]{3,32}">
        </label>
        <label>Hasło (min. 8 znaków)
            <input type="password" name="password" required minlength="8">
        </label>
        <label>Powtórz hasło
            <input type="password" name="password_confirm" required minlength="8">
        </label>
        <button type="submit">Utwórz konto administratora</button>
    </form>
</div>
</body>
</html>
