<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AuditLog;
use App\Auth;
use App\Csrf;
use App\Database;
use PDO;
use PDOException;

use function App\flash;
use function App\redirect;
use function App\view;

final class AdminController
{
    public static function users(): void
    {
        Auth::requireAdmin();
        $stmt = Database::connection()->query('SELECT * FROM users ORDER BY id ASC');
        view('admin_users', ['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function createUser(): void
    {
        Auth::requireAdmin();
        Csrf::verifyRequest('admin_users');

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $canUpload = isset($_POST['can_upload']) ? 1 : 0;
        $canDelete = isset($_POST['can_delete']) ? 1 : 0;
        $canShare  = isset($_POST['can_share']) ? 1 : 0;

        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $username)) {
            flash('error', 'Login może zawierać litery, cyfry, "._-" (3-32 znaki).');
            redirect('admin_users');
        }
        if (strlen($password) < 8) {
            flash('error', 'Hasło musi mieć co najmniej 8 znaków.');
            redirect('admin_users');
        }

        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO users (username, password_hash, role, can_upload, can_delete, can_share, is_active, created_at)
                 VALUES (:u, :p, :r, :cu, :cd, :cs, 1, :now)'
            );
            $stmt->execute([
                'u'   => $username,
                'p'   => password_hash($password, PASSWORD_DEFAULT),
                'r'   => $role,
                'cu'  => $canUpload,
                'cd'  => $canDelete,
                'cs'  => $canShare,
                'now' => time(),
            ]);
            $admin = Auth::user();
            AuditLog::log((int) $admin['id'], $admin['username'], 'user_create', $username);
            flash('success', "Utworzono użytkownika {$username}.");
        } catch (PDOException) {
            flash('error', 'Taki login już istnieje.');
        }

        redirect('admin_users');
    }

    public static function updateUser(): void
    {
        Auth::requireAdmin();
        Csrf::verifyRequest('admin_users');

        $id = (int) ($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $canUpload = isset($_POST['can_upload']) ? 1 : 0;
        $canDelete = isset($_POST['can_delete']) ? 1 : 0;
        $canShare  = isset($_POST['can_share']) ? 1 : 0;
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        $stmt = Database::connection()->prepare(
            'UPDATE users SET role = :r, can_upload = :cu, can_delete = :cd, can_share = :cs, is_active = :active WHERE id = :id'
        );
        $stmt->execute(['r' => $role, 'cu' => $canUpload, 'cd' => $canDelete, 'cs' => $canShare, 'active' => $isActive, 'id' => $id]);

        $newPassword = (string) ($_POST['new_password'] ?? '');
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                flash('error', 'Nowe hasło musi mieć co najmniej 8 znaków.');
                redirect('admin_users');
            }
            $stmt = Database::connection()->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
            $stmt->execute(['p' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $id]);
        }

        $admin = Auth::user();
        AuditLog::log((int) $admin['id'], $admin['username'], 'user_update', 'id=' . $id);
        flash('success', 'Zaktualizowano użytkownika.');
        redirect('admin_users');
    }

    public static function deleteUser(): void
    {
        Auth::requireAdmin();
        Csrf::verifyRequest('admin_users');

        $id = (int) ($_POST['id'] ?? 0);
        $admin = Auth::user();
        if ($id === (int) $admin['id']) {
            flash('error', 'Nie możesz usunąć własnego konta.');
            redirect('admin_users');
        }

        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        AuditLog::log((int) $admin['id'], $admin['username'], 'user_delete', 'id=' . $id);
        flash('success', 'Użytkownik usunięty.');
        redirect('admin_users');
    }

    public static function audit(): void
    {
        Auth::requireAdmin();
        view('admin_audit', ['logs' => AuditLog::recent(200)]);
    }
}
