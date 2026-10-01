<?php
declare(strict_types=1);

namespace App;

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak stack traces to visitors

define('APP_ROOT', dirname(__DIR__));
define('VIEWS_PATH', APP_ROOT . '/views');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (str_starts_with($relative, 'Controllers\\')) {
        $file = APP_ROOT . '/controllers/' . substr($relative, strlen('Controllers\\')) . '.php';
    } else {
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    }
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

date_default_timezone_set(Config::get('timezone', 'UTC'));

// --- Secure session setup ---
$sessionName = Config::get('session_name', 'cytrus_sid');
$lifetime    = (int) Config::get('session_lifetime', 28800);
$isHttps     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (bool) Config::get('force_https', false);

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['_started'])) {
    $_SESSION['_started'] = time();
}
if (isset($_SESSION['_last_seen']) && (time() - $_SESSION['_last_seen']) > $lifetime) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['_last_seen'] = time();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'");
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

Database::init();
