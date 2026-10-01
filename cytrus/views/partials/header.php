<?php
/** @var array|null $user */
use App\Auth;
use App\Csrf;

use function App\e;
use function App\get_flashes;

$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cytrus Files</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand"><a href="index.php?p=files">📁 Cytrus Files</a></div>
    <?php if ($user): ?>
    <nav>
        <a href="index.php?p=files">Pliki</a>
        <a href="index.php?p=shares">Udostępnione linki</a>
        <?php if (Auth::isAdmin()): ?>
            <a href="index.php?p=admin_users">Użytkownicy</a>
            <a href="index.php?p=admin_audit">Dziennik zdarzeń</a>
        <?php endif; ?>
        <span class="user">Zalogowano jako <strong><?= e($user['username']) ?></strong></span>
        <form method="post" action="index.php?p=logout" class="inline">
            <?= Csrf::field() ?>
            <button type="submit" class="link-button">Wyloguj</button>
        </form>
    </nav>
    <?php endif; ?>
</header>
<main class="container">
<?php foreach (get_flashes() as $flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
