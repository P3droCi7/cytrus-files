<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\FilesController;
use App\Controllers\SetupController;
use App\Controllers\ShareController;
use App\Database;

use function App\redirect;

function cytrus_not_found(): void
{
    http_response_code(404);
    echo '404 - nie znaleziono strony.';
}

$page = (string) ($_GET['p'] ?? 'files');
$method = $_SERVER['REQUEST_METHOD'];

// Force the one-time setup wizard until the first admin account exists.
$usersCount = (int) Database::connection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($usersCount === 0 && !in_array($page, ['setup', 's'], true)) {
    redirect('setup');
}

try {
    match (true) {
        $page === 'setup' && $method === 'GET'               => SetupController::show(),
        $page === 'setup' && $method === 'POST'              => SetupController::create(),
        $page === 's'                                         => ShareController::show(),
        $page === 'login' && $method === 'GET'               => AuthController::showLogin(),
        $page === 'login' && $method === 'POST'              => AuthController::login(),
        $page === 'logout' && $method === 'POST'             => AuthController::logout(),
        $page === 'files'                                     => FilesController::index(),
        $page === 'upload' && $method === 'POST'             => FilesController::upload(),
        $page === 'upload_chunk' && $method === 'POST'       => FilesController::uploadChunk(),
        $page === 'download'                                  => FilesController::download(),
        $page === 'mkdir' && $method === 'POST'              => FilesController::mkdir(),
        $page === 'delete' && $method === 'POST'             => FilesController::delete(),
        $page === 'rename' && $method === 'POST'             => FilesController::rename(),
        $page === 'shares'                                    => FilesController::shares(),
        $page === 'share_create' && $method === 'POST'       => FilesController::createShare(),
        $page === 'share_revoke' && $method === 'POST'       => FilesController::revokeShare(),
        $page === 'admin_users' && $method === 'GET'         => AdminController::users(),
        $page === 'admin_users_create' && $method === 'POST' => AdminController::createUser(),
        $page === 'admin_users_update' && $method === 'POST' => AdminController::updateUser(),
        $page === 'admin_users_delete' && $method === 'POST' => AdminController::deleteUser(),
        $page === 'admin_audit' && $method === 'GET'         => AdminController::audit(),
        default                                               => cytrus_not_found(),
    };
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[cytrus] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo 'Wystąpił błąd serwera. Spróbuj ponownie później.';
}
