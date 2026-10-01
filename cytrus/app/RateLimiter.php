<?php
declare(strict_types=1);

namespace App;

use PDO;

final class RateLimiter
{
    public static function isLocked(string $identifier): bool
    {
        $row = self::row($identifier);
        return $row !== false && $row['locked_until'] !== null && (int) $row['locked_until'] > time();
    }

    public static function remainingLockSeconds(string $identifier): int
    {
        $row = self::row($identifier);
        if ($row === false || $row['locked_until'] === null) {
            return 0;
        }
        return max(0, (int) $row['locked_until'] - time());
    }

    public static function registerFailure(string $identifier): void
    {
        $pdo = Database::connection();
        $maxAttempts = (int) Config::get('max_login_attempts', 5);
        $lockSeconds = (int) Config::get('login_lockout_seconds', 300);
        $row = self::row($identifier);

        if ($row === false) {
            $stmt = $pdo->prepare('INSERT INTO login_throttle (identifier, attempts, first_attempt_at, locked_until) VALUES (:id, 1, :now, NULL)');
            $stmt->execute(['id' => $identifier, 'now' => time()]);
            return;
        }

        $attempts = (int) $row['attempts'] + 1;
        $lockedUntil = $attempts >= $maxAttempts ? time() + $lockSeconds : null;

        $stmt = $pdo->prepare('UPDATE login_throttle SET attempts = :attempts, locked_until = :locked_until WHERE identifier = :id');
        $stmt->execute(['attempts' => $attempts, 'locked_until' => $lockedUntil, 'id' => $identifier]);
    }

    public static function reset(string $identifier): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM login_throttle WHERE identifier = :id');
        $stmt->execute(['id' => $identifier]);
    }

    private static function row(string $identifier): array|false
    {
        $stmt = Database::connection()->prepare('SELECT * FROM login_throttle WHERE identifier = :id');
        $stmt->execute(['id' => $identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
