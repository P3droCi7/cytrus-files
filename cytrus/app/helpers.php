<?php
declare(strict_types=1);

namespace App;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** @param array<string,scalar> $params */
function redirect(string $page, array $params = []): never
{
    $params['p'] = $page;
    header('Location: index.php?' . http_build_query($params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/** Appends the file's mtime as a query string so CDN/browser caches fetch fresh assets after each deploy. */
function asset_version(string $relativePath): string
{
    $path = APP_ROOT . '/' . ltrim($relativePath, '/');
    $mtime = @filemtime($path);
    return $relativePath . ($mtime !== false ? '?v=' . $mtime : '');
}

/** @param array<string,mixed> $vars */
function view(string $name, array $vars = [], bool $withChrome = true): void
{
    extract($vars, EXTR_SKIP);
    if ($withChrome) {
        require VIEWS_PATH . '/partials/header.php';
    }
    require VIEWS_PATH . '/' . $name . '.php';
    if ($withChrome) {
        require VIEWS_PATH . '/partials/footer.php';
    }
}
