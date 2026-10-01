<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function init(): void
    {
        self::connection();
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dataDir = (string) Config::get('data_dir');
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0700, true);
        }

        $dbFile = $dataDir . '/app.sqlite';
        $isNew  = !is_file($dbFile);

        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        self::$pdo = $pdo;
        self::migrate();

        if ($isNew) {
            @chmod($dbFile, 0600);
        }

        return $pdo;
    }

    private static function migrate(): void
    {
        $pdo = self::$pdo;

        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            can_upload INTEGER NOT NULL DEFAULT 1,
            can_delete INTEGER NOT NULL DEFAULT 0,
            can_share INTEGER NOT NULL DEFAULT 1,
            is_active INTEGER NOT NULL DEFAULT 1,
            failed_attempts INTEGER NOT NULL DEFAULT 0,
            locked_until INTEGER NULL,
            last_login_at INTEGER NULL,
            created_at INTEGER NOT NULL
        )
        SQL);

        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS share_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT NOT NULL UNIQUE,
            path TEXT NOT NULL,
            created_by INTEGER NOT NULL,
            password_hash TEXT NULL,
            expires_at INTEGER NULL,
            max_downloads INTEGER NULL,
            downloads_count INTEGER NOT NULL DEFAULT 0,
            is_revoked INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            FOREIGN KEY(created_by) REFERENCES users(id)
        )
        SQL);

        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            username TEXT NULL,
            action TEXT NOT NULL,
            detail TEXT NULL,
            ip TEXT NULL,
            created_at INTEGER NOT NULL
        )
        SQL);

        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS login_throttle (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier TEXT NOT NULL UNIQUE,
            attempts INTEGER NOT NULL DEFAULT 0,
            first_attempt_at INTEGER NOT NULL,
            locked_until INTEGER NULL
        )
        SQL);
    }
}
