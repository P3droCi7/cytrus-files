<?php
declare(strict_types=1);

namespace App;

use PDO;

final class ShareManager
{
    public static function create(int $userId, string $relativePath, ?string $password, ?int $expiresInSeconds, ?int $maxDownloads): array
    {
        $token = bin2hex(random_bytes((int) Config::get('share_token_bytes', 24)));
        $passwordHash = ($password !== null && $password !== '') ? password_hash($password, PASSWORD_DEFAULT) : null;
        $expiresAt = $expiresInSeconds !== null ? time() + $expiresInSeconds : null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO share_links (token, path, created_by, password_hash, expires_at, max_downloads, created_at)
             VALUES (:token, :path, :uid, :phash, :expires, :maxdl, :now)'
        );
        $stmt->execute([
            'token'   => $token,
            'path'    => trim($relativePath, '/'),
            'uid'     => $userId,
            'phash'   => $passwordHash,
            'expires' => $expiresAt,
            'maxdl'   => $maxDownloads,
            'now'     => time(),
        ]);

        return ['token' => $token, 'id' => (int) Database::connection()->lastInsertId()];
    }

    public static function find(string $token): array|false
    {
        $stmt = Database::connection()->prepare('SELECT * FROM share_links WHERE token = :t');
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) $row['is_revoked'] === 1) {
            return false;
        }
        if ($row['expires_at'] !== null && (int) $row['expires_at'] < time()) {
            return false;
        }
        if ($row['max_downloads'] !== null && (int) $row['downloads_count'] >= (int) $row['max_downloads']) {
            return false;
        }
        return $row;
    }

    public static function verifyPassword(array $share, ?string $password): bool
    {
        if ($share['password_hash'] === null) {
            return true;
        }
        return $password !== null && password_verify($password, $share['password_hash']);
    }

    public static function registerDownload(array $share): void
    {
        $stmt = Database::connection()->prepare('UPDATE share_links SET downloads_count = downloads_count + 1 WHERE id = :id');
        $stmt->execute(['id' => $share['id']]);
    }

    public static function revoke(int $id, int $userId, bool $isAdmin): bool
    {
        $sql = $isAdmin
            ? 'UPDATE share_links SET is_revoked = 1 WHERE id = :id'
            : 'UPDATE share_links SET is_revoked = 1 WHERE id = :id AND created_by = :uid';
        $params = $isAdmin ? ['id' => $id] : ['id' => $id, 'uid' => $userId];
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function listForUser(int $userId, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            $stmt = Database::connection()->query(
                'SELECT s.*, u.username FROM share_links s JOIN users u ON u.id = s.created_by ORDER BY s.id DESC'
            );
        } else {
            $stmt = Database::connection()->prepare('SELECT * FROM share_links WHERE created_by = :uid ORDER BY id DESC');
            $stmt->execute(['uid' => $userId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
