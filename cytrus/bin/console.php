<?php
declare(strict_types=1);

// Command line helper for server administration (SSH), independent of the web session stack.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dostęp tylko z linii poleceń.');
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = str_starts_with($relative, 'Controllers\\')
        ? __DIR__ . '/../controllers/' . substr($relative, strlen('Controllers\\')) . '.php'
        : __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Database;

$command = $argv[1] ?? null;

switch ($command) {
    case 'create-admin':
        $username = $argv[2] ?? null;
        $password = $argv[3] ?? null;
        if (!$username || !$password) {
            fwrite(STDERR, "Użycie: php bin/console.php create-admin <login> <haslo>\n");
            exit(1);
        }
        if (strlen($password) < 8) {
            fwrite(STDERR, "Hasło musi mieć co najmniej 8 znaków.\n");
            exit(1);
        }
        $stmt = Database::connection()->prepare(
            "INSERT INTO users (username, password_hash, role, can_upload, can_delete, can_share, is_active, created_at)
             VALUES (:u, :p, 'admin', 1, 1, 1, 1, :now)
             ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash, role = 'admin', is_active = 1"
        );
        $stmt->execute(['u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT), 'now' => time()]);
        echo "Administrator '{$username}' utworzony/zaktualizowany.\n";
        break;

    case 'list-users':
        foreach (Database::connection()->query('SELECT id, username, role, is_active FROM users ORDER BY id') as $row) {
            printf("%d\t%s\t%s\t%s\n", $row['id'], $row['username'], $row['role'], $row['is_active'] ? 'aktywny' : 'zablokowany');
        }
        break;

    case 'disable-user':
        $username = $argv[2] ?? null;
        if (!$username) {
            fwrite(STDERR, "Użycie: php bin/console.php disable-user <login>\n");
            exit(1);
        }
        $stmt = Database::connection()->prepare('UPDATE users SET is_active = 0 WHERE username = :u');
        $stmt->execute(['u' => $username]);
        echo "Użytkownik '{$username}' zablokowany.\n";
        break;

    default:
        echo "Dostępne polecenia:\n";
        echo "  php bin/console.php create-admin <login> <haslo>\n";
        echo "  php bin/console.php list-users\n";
        echo "  php bin/console.php disable-user <login>\n";
}
