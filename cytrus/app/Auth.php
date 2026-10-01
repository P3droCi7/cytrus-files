<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    private static array|null|false $cachedUser = false;

    public static function attempt(string $username, string $password): array
    {
        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($username);

        if (RateLimiter::isLocked($identifier)) {
            return ['ok' => false, 'reason' => 'locked', 'wait' => RateLimiter::remainingLockSeconds($identifier)];
        }

        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            RateLimiter::registerFailure($identifier);
            AuditLog::log($user['id'] ?? null, $username, 'login_failed');
            return ['ok' => false, 'reason' => 'invalid'];
        }

        RateLimiter::reset($identifier);
        self::login($user);
        AuditLog::log((int) $user['id'], $username, 'login_success');

        return ['ok' => true, 'user' => $user];
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$cachedUser = false;

        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = :t WHERE id = :id');
        $stmt->execute(['t' => time(), 'id' => $user['id']]);
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user) {
            AuditLog::log((int) $user['id'], $user['username'], 'logout');
        }
        $_SESSION = [];
        session_destroy();
        self::$cachedUser = false;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== false) {
            return self::$cachedUser;
        }
        if (empty($_SESSION['user_id'])) {
            return self::$cachedUser = null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return self::$cachedUser = ($user ?: null);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Brak uprawnień.';
            exit;
        }
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return match ($permission) {
            'upload' => (bool) $user['can_upload'],
            'delete' => (bool) $user['can_delete'],
            'share'  => (bool) $user['can_share'],
            default  => false,
        };
    }
}
