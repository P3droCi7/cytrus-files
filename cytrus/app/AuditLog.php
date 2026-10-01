<?php
declare(strict_types=1);

namespace App;

use PDO;

final class AuditLog
{
    public static function log(?int $userId, ?string $username, string $action, ?string $detail = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_log (user_id, username, action, detail, ip, created_at) VALUES (:uid, :uname, :action, :detail, :ip, :now)'
        );
        $stmt->execute([
            'uid'    => $userId,
            'uname'  => $username,
            'action' => $action,
            'detail' => $detail,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'now'    => time(),
        ]);
    }

    public static function recent(int $limit = 100): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM audit_log ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
