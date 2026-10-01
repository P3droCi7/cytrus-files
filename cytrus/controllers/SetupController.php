<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AuditLog;
use App\Auth;
use App\Csrf;
use App\Database;

use function App\flash;
use function App\redirect;
use function App\view;

final class SetupController
{
    public static function show(): void
    {
        if (self::hasUsers()) {
            http_response_code(404);
            echo 'Nie znaleziono.';
            exit;
        }
        view('setup', [], false);
    }

    public static function create(): void
    {
        if (self::hasUsers()) {
            http_response_code(404);
            exit;
        }
        Csrf::verifyRequest('setup');

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $username)) {
            flash('error', 'Login może zawierać litery, cyfry, "._-" (3-32 znaki).');
            redirect('setup');
        }
        if (strlen($password) < 8 || $password !== $confirm) {
            flash('error', 'Hasła muszą być identyczne i mieć co najmniej 8 znaków.');
            redirect('setup');
        }

        $stmt = Database::connection()->prepare(
            "INSERT INTO users (username, password_hash, role, can_upload, can_delete, can_share, is_active, created_at)
             VALUES (:u, :p, 'admin', 1, 1, 1, 1, :now)"
        );
        $stmt->execute([
            'u'   => $username,
            'p'   => password_hash($password, PASSWORD_DEFAULT),
            'now' => time(),
        ]);

        AuditLog::log(null, $username, 'setup_admin_created');
        flash('success', 'Konto administratora utworzone. Zaloguj się.');
        redirect('login');
    }

    private static function hasUsers(): bool
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) FROM users');
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
