<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AuditLog;
use App\Auth;
use App\Csrf;
use App\FileManager;
use App\ShareManager;

use function App\flash;
use function App\redirect;
use function App\view;

final class FilesController
{
    public static function index(): void
    {
        Auth::requireLogin();
        FileManager::cleanupStaleChunkSessions();
        $dir = trim((string) ($_GET['dir'] ?? ''), '/');
        $entries = FileManager::listDir($dir);

        if ($entries === false) {
            flash('error', 'Folder nie istnieje.');
            redirect('files');
        }

        view('files', [
            'entries'     => $entries,
            'currentDir'  => $dir,
            'breadcrumbs' => self::breadcrumbs($dir),
            'user'        => Auth::user(),
        ]);
    }

    /** Receives one chunk of a large file (JSON API, called by assets/js/upload.js). Keeps each request short to stay under proxy timeouts. */
    public static function uploadChunk(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Wymagane logowanie.']);
            return;
        }
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF nieprawidłowy, odśwież stronę.']);
            return;
        }
        if (!Auth::can('upload')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Brak uprawnień do przesyłania plików.']);
            return;
        }

        $uploadId = (string) ($_POST['upload_id'] ?? '');
        $chunkIndex = (int) ($_POST['chunk_index'] ?? -1);
        $totalChunks = (int) ($_POST['total_chunks'] ?? 0);
        $dir = trim((string) ($_POST['dir'] ?? ''), '/');
        $filename = (string) ($_POST['filename'] ?? '');

        if (
            !FileManager::isValidUploadId($uploadId)
            || $totalChunks < 1 || $totalChunks > 100000
            || $chunkIndex < 0 || $chunkIndex >= $totalChunks
            || $filename === ''
            || FileManager::resolve($dir) === false
        ) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe żądanie fragmentu.']);
            return;
        }

        $chunk = $_FILES['chunk'] ?? null;
        if (!$chunk) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Brak fragmentu pliku w żądaniu.']);
            return;
        }

        $stored = FileManager::storeChunk($uploadId, $chunkIndex, $chunk);
        if (!$stored['ok']) {
            echo json_encode($stored);
            return;
        }

        if ($chunkIndex < $totalChunks - 1) {
            echo json_encode(['ok' => true, 'done' => false]);
            return;
        }

        // Last chunk: assembling a multi-GB file can take longer than the proxy's response timeout,
        // so acknowledge receipt immediately and keep assembling after the client connection is closed.
        echo json_encode(['ok' => true, 'done' => false, 'processing' => true]);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }

        $result = FileManager::assembleChunks($uploadId, $totalChunks, $dir, $filename);
        if ($result['ok']) {
            $user = Auth::user();
            AuditLog::log((int) $user['id'], $user['username'], 'upload', $dir . '/' . $result['name']);
        }
    }

    /** Polled by the browser after the last chunk to learn when background assembly finished. */
    public static function uploadStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['done' => false, 'ok' => false, 'error' => 'Wymagane logowanie.']);
            return;
        }

        $uploadId = (string) ($_GET['upload_id'] ?? '');
        if (!FileManager::isValidUploadId($uploadId)) {
            http_response_code(400);
            echo json_encode(['done' => false, 'ok' => false, 'error' => 'Nieprawidłowe żądanie.']);
            return;
        }

        $status = FileManager::readStatus($uploadId);
        echo json_encode($status ?? ['done' => false]);
    }

    private static function breadcrumbs(string $dir): array
    {
        $crumbs = [['name' => 'Start', 'path' => '']];
        if ($dir === '') {
            return $crumbs;
        }
        $accum = [];
        foreach (explode('/', $dir) as $part) {
            $accum[] = $part;
            $crumbs[] = ['name' => $part, 'path' => implode('/', $accum)];
        }
        return $crumbs;
    }

    public static function upload(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest('files');

        if (!Auth::can('upload')) {
            flash('error', 'Nie masz uprawnień do przesyłania plików.');
            redirect('files');
        }

        $dir = trim((string) ($_POST['dir'] ?? ''), '/');
        if (FileManager::resolve($dir) === false) {
            flash('error', 'Nieprawidłowy folder docelowy.');
            redirect('files');
        }

        $files = $_FILES['files'] ?? null;
        if (!$files || !is_array($files['name'])) {
            flash('error', 'Nie wybrano żadnego pliku.');
            redirect('files', ['dir' => $dir]);
        }

        $user = Auth::user();
        $okCount = 0;
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $single = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $result = FileManager::saveUpload($single, $dir);
            if ($result['ok']) {
                $okCount++;
                AuditLog::log((int) $user['id'], $user['username'], 'upload', $dir . '/' . $result['name']);
            } else {
                flash('error', $result['error']);
            }
        }

        if ($okCount > 0) {
            flash('success', "Przesłano {$okCount} plik(ów).");
        }

        redirect('files', ['dir' => $dir]);
    }

    public static function download(): void
    {
        Auth::requireLogin();
        $relative = (string) ($_GET['path'] ?? '');
        $abs = FileManager::resolve($relative);

        if ($abs === false || !is_file($abs)) {
            http_response_code(404);
            echo 'Plik nie znaleziony.';
            exit;
        }

        $user = Auth::user();
        AuditLog::log((int) $user['id'], $user['username'], 'download', $relative);
        FileManager::stream($abs, basename($abs));
    }

    public static function mkdir(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest('files');

        if (!Auth::can('upload')) {
            flash('error', 'Brak uprawnień.');
            redirect('files');
        }

        $dir = trim((string) ($_POST['dir'] ?? ''), '/');
        $name = (string) ($_POST['name'] ?? '');

        if (FileManager::createFolder($dir, $name)) {
            $user = Auth::user();
            flash('success', 'Folder utworzony.');
            AuditLog::log((int) $user['id'], $user['username'], 'mkdir', $dir . '/' . $name);
        } else {
            flash('error', 'Nie udało się utworzyć folderu (może już istnieć).');
        }

        redirect('files', ['dir' => $dir]);
    }

    public static function delete(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest('files');

        if (!Auth::can('delete')) {
            flash('error', 'Nie masz uprawnień do usuwania plików.');
            redirect('files');
        }

        $relative = (string) ($_POST['path'] ?? '');
        $dir = dirname($relative);
        $dir = $dir === '.' ? '' : $dir;

        if (FileManager::delete($relative)) {
            $user = Auth::user();
            flash('success', 'Usunięto.');
            AuditLog::log((int) $user['id'], $user['username'], 'delete', $relative);
        } else {
            flash('error', 'Nie udało się usunąć.');
        }

        redirect('files', ['dir' => $dir]);
    }

    public static function rename(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest('files');

        if (!Auth::can('delete')) {
            flash('error', 'Brak uprawnień.');
            redirect('files');
        }

        $relative = (string) ($_POST['path'] ?? '');
        $newName = (string) ($_POST['new_name'] ?? '');
        $dir = dirname($relative);
        $dir = $dir === '.' ? '' : $dir;

        if (FileManager::rename($relative, $newName)) {
            $user = Auth::user();
            flash('success', 'Zmieniono nazwę.');
            AuditLog::log((int) $user['id'], $user['username'], 'rename', $relative . ' -> ' . $newName);
        } else {
            flash('error', 'Nie udało się zmienić nazwy.');
        }

        redirect('files', ['dir' => $dir]);
    }

    public static function shares(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $shares = ShareManager::listForUser((int) $user['id'], Auth::isAdmin());
        view('shares', ['shares' => $shares, 'isAdmin' => Auth::isAdmin()]);
    }

    public static function createShare(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest('files');

        if (!Auth::can('share')) {
            flash('error', 'Nie masz uprawnień do udostępniania plików.');
            redirect('files');
        }

        $relative = trim((string) ($_POST['path'] ?? ''), '/');
        if (FileManager::resolve($relative) === false) {
            flash('error', 'Nieprawidłowa ścieżka.');
            redirect('files');
        }

        $password = trim((string) ($_POST['password'] ?? ''));
        $expiresDays = (int) ($_POST['expires_days'] ?? 0);
        $maxDownloads = (int) ($_POST['max_downloads'] ?? 0);

        $user = Auth::user();
        $share = ShareManager::create(
            (int) $user['id'],
            $relative,
            $password !== '' ? $password : null,
            $expiresDays > 0 ? $expiresDays * 86400 : null,
            $maxDownloads > 0 ? $maxDownloads : null
        );

        AuditLog::log((int) $user['id'], $user['username'], 'share_create', $relative);
        flash('success', 'Link utworzony: ' . self::shareUrl($share['token']));
        redirect('shares');
    }

    public static function shareUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
        return "{$scheme}://{$host}{$base}/index.php?p=s&t={$token}";
    }

    public static function revokeShare(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest('shares');

        $id = (int) ($_POST['id'] ?? 0);
        $user = Auth::user();
        if (ShareManager::revoke($id, (int) $user['id'], Auth::isAdmin())) {
            flash('success', 'Link odwołany.');
        } else {
            flash('error', 'Nie udało się odwołać linku.');
        }
        redirect('shares');
    }
}
