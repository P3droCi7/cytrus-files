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
<title>Logowanie - Cytrus Files</title>
<link rel="stylesheet" href="<?= e(\App\asset_version('assets/css/style.css')) ?>">
</head>
<body class="centered">
<div class="card">
    <h1>📁 Cytrus Files</h1>
    <?php foreach (get_flashes() as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="index.php?p=login">
        <?= Csrf::field() ?>
        <label>Login
            <input type="text" name="username" required autofocus autocomplete="username">
        </label>
        <label>Hasło
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit">Zaloguj się</button>
    </form>
</div>
</body>
</html>
