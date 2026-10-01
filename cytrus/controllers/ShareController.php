<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Csrf;
use App\FileManager;
use App\ShareManager;

use function App\view;

final class ShareController
{
    public static function show(): void
    {
        $token = (string) ($_GET['t'] ?? '');
        $share = ShareManager::find($token);

        if ($share === false) {
            http_response_code(404);
            view('share_invalid', [], false);
            return;
        }

        $abs = FileManager::resolve($share['path']);
        if ($abs === false || !is_file($abs)) {
            http_response_code(404);
            view('share_invalid', [], false);
            return;
        }

        $needsPassword = $share['password_hash'] !== null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verifyRequest('s', ['t' => $token]);
            $password = (string) ($_POST['password'] ?? '');
            if (!ShareManager::verifyPassword($share, $password)) {
                $error = 'Nieprawidłowe hasło.';
            } else {
                ShareManager::registerDownload($share);
                FileManager::stream($abs, basename($abs));
            }
        } elseif (!$needsPassword) {
            ShareManager::registerDownload($share);
            FileManager::stream($abs, basename($abs));
        }

        view('share_download', [
            'token'         => $token,
            'needsPassword' => $needsPassword,
            'error'         => $error,
            'name'          => basename($abs),
            'size'          => FileManager::humanSize((int) filesize($abs)),
        ], false);
    }
}
