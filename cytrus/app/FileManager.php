<?php
declare(strict_types=1);

namespace App;

final class FileManager
{
    public static function root(): string
    {
        $root = (string) Config::get('storage_root');
        if (!is_dir($root)) {
            mkdir($root, 0775, true);
        }
        $real = realpath($root);
        return $real !== false ? rtrim($real, '/') : rtrim($root, '/');
    }

    /** Resolves a user supplied relative path safely within the storage root. Returns false if it would escape the root. */
    public static function resolve(string $relative): string|false
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = trim($relative, '/');
        $root = self::root();

        if ($relative === '' || $relative === '.') {
            return $root;
        }

        $segments = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return false; // never allow walking above root
            }
            $segments[] = $segment;
        }

        $candidate = $root . '/' . implode('/', $segments);
        $real = realpath($candidate);

        if ($real === false) {
            // target may not exist yet (e.g. a new folder about to be created)
            if (!str_starts_with($candidate . '/', $root . '/')) {
                return false;
            }
            return $candidate;
        }

        if ($real !== $root && !str_starts_with($real . '/', $root . '/')) {
            return false;
        }

        return $real;
    }

    public static function listDir(string $relative): array|false
    {
        $abs = self::resolve($relative);
        if ($abs === false || !is_dir($abs)) {
            return false;
        }

        $entries = [];
        $relTrimmed = trim($relative, '/');
        foreach (scandir($abs) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (self::isProtectedName($name)) {
                continue;
            }
            $full = $abs . '/' . $name;
            $relPath = $relTrimmed === '' ? $name : $relTrimmed . '/' . $name;
            $entries[] = [
                'name'     => $name,
                'is_dir'   => is_dir($full),
                'size'     => is_file($full) ? (int) filesize($full) : 0,
                'mtime'    => filemtime($full) ?: 0,
                'relative' => $relPath,
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    /** Returns filesystem capacity for the storage volume, or null when PHP cannot report it. */
    public static function diskUsage(): ?array
    {
        $root = self::root();
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);
        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $total = (int) $total;
        $free = min($total, max(0, (int) $free));
        $used = $total - $free;

        return [
            'total'     => $total,
            'free'      => $free,
            'used'      => $used,
            'percent'   => (int) round(($used / $total) * 100),
        ];
    }

    public static function sanitizeName(string $name): string
    {
        $name = str_replace(['/', '\\', "\0"], '', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = trim($name, " .\t\n\r\0\x0B");
        if ($name === '') {
            $name = 'plik_' . bin2hex(random_bytes(4));
        }
        return mb_substr($name, 0, 180);
    }

    public static function createFolder(string $relativeParent, string $name): bool
    {
        $parent = self::resolve($relativeParent);
        if ($parent === false || !is_dir($parent)) {
            return false;
        }
        $safeName = self::sanitizeName($name);
        $target = $parent . '/' . $safeName;
        if (file_exists($target)) {
            return false;
        }
        return mkdir($target, 0775);
    }

    public static function delete(string $relative): bool
    {
        $abs = self::resolve($relative);
        if ($abs === false || $abs === self::root() || !file_exists($abs)) {
            return false;
        }
        if (self::isProtectedName(basename($abs))) {
            return false;
        }
        if (is_dir($abs)) {
            if (self::containsProtectedEntry($abs)) {
                return false;
            }
            return self::deleteDirRecursive($abs);
        }
        return unlink($abs);
    }

    private static function isProtectedName(string $name): bool
    {
        return in_array(strtolower($name), ['.gitkeep', '.htaccess'], true);
    }

    private static function containsProtectedEntry(string $dir): bool
    {
        $items = scandir($dir);
        if ($items === false) {
            return true;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (self::isProtectedName($item)) {
                return true;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && self::containsProtectedEntry($path)) {
                return true;
            }
        }
        return false;
    }

    private static function deleteDirRecursive(string $dir): bool
    {
        $items = scandir($dir);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::deleteDirRecursive($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    public static function rename(string $relative, string $newName): bool
    {
        $abs = self::resolve($relative);
        if ($abs === false || $abs === self::root() || !file_exists($abs)) {
            return false;
        }
        if (self::isProtectedName(basename($abs))) {
            return false;
        }
        $safeName = self::sanitizeName($newName);
        if (self::isProtectedName($safeName)) {
            return false;
        }
        $target = dirname($abs) . '/' . $safeName;
        if (file_exists($target)) {
            return false;
        }
        return rename($abs, $target);
    }

    public static function isBlockedExtension(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, (array) Config::get('blocked_extensions', []), true);
    }

    /** @param array<string,mixed> $file single normalized entry from $_FILES */
    public static function saveUpload(array $file, string $relativeDir): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Błąd przesyłania pliku (kod ' . $file['error'] . ').'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Nieprawidłowe żądanie przesyłania pliku.'];
        }

        $originalName = self::sanitizeName($file['name']);
        if (self::isBlockedExtension($originalName)) {
            return ['ok' => false, 'error' => "Niedozwolony typ pliku: {$originalName}"];
        }

        $dir = self::resolve($relativeDir);
        if ($dir === false || !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Nieprawidłowy folder docelowy.'];
        }

        $target = self::uniqueTarget($dir, $originalName);
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => "Nie udało się zapisać pliku {$originalName}."];
        }
        chmod($target, 0664);

        return ['ok' => true, 'path' => $target, 'name' => basename($target)];
    }

    private static function uniqueTarget(string $dir, string $name): string
    {
        $target = $dir . '/' . $name;
        if (!file_exists($target)) {
            return $target;
        }
        $pathInfo = pathinfo($name);
        $base = $pathInfo['filename'];
        $ext  = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $i = 1;
        do {
            $candidate = $dir . '/' . $base . ' (' . $i . ')' . $ext;
            $i++;
        } while (file_exists($candidate));
        return $candidate;
    }

    public static function isValidUploadId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{16,64}$/', $id);
    }

    private static function chunkDir(string $uploadId): string
    {
        $dir = rtrim((string) Config::get('data_dir'), '/') . '/uploads_tmp/' . $uploadId;
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return $dir;
    }

    /** @param array<string,mixed> $file single normalized entry from $_FILES */
    public static function storeChunk(string $uploadId, int $index, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Błąd przesyłania fragmentu (kod ' . $file['error'] . ').'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Nieprawidłowe żądanie przesyłania fragmentu.'];
        }
        $target = self::chunkDir($uploadId) . '/' . $index . '.part';
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'Nie udało się zapisać fragmentu pliku.'];
        }
        return ['ok' => true];
    }

    /** Concatenates all received chunks into the final file, in order, then writes a status file for polling. */
    public static function assembleChunks(string $uploadId, int $totalChunks, string $relativeDir, string $originalName): array
    {
        $chunkDir = self::chunkDir($uploadId);
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!is_file($chunkDir . '/' . $i . '.part')) {
                $result = ['ok' => false, 'error' => 'Brakuje fragmentu pliku nr ' . $i . '.'];
                self::writeStatus($uploadId, $result);
                return $result;
            }
        }

        $destDir = self::resolve($relativeDir);
        if ($destDir === false || !is_dir($destDir)) {
            $result = ['ok' => false, 'error' => 'Nieprawidłowy folder docelowy.'];
            self::writeStatus($uploadId, $result);
            self::deleteDirRecursive($chunkDir);
            return $result;
        }

        $safeName = self::sanitizeName($originalName);
        if (self::isBlockedExtension($safeName)) {
            $result = ['ok' => false, 'error' => "Niedozwolony typ pliku: {$safeName}"];
            self::writeStatus($uploadId, $result);
            self::deleteDirRecursive($chunkDir);
            return $result;
        }

        $target = self::uniqueTarget($destDir, $safeName);
        $out = fopen($target, 'wb');
        if ($out === false) {
            $result = ['ok' => false, 'error' => 'Nie udało się utworzyć pliku docelowego.'];
            self::writeStatus($uploadId, $result);
            self::deleteDirRecursive($chunkDir);
            return $result;
        }
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $chunkDir . '/' . $i . '.part';
            $in = fopen($partPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            unlink($partPath); // free disk space as we go, no need to keep raw fragments after copying
        }
        fclose($out);
        chmod($target, 0664);

        $result = ['ok' => true, 'name' => basename($target)];
        self::writeStatus($uploadId, $result);
        return $result;
    }

    /** Persists the final outcome so the browser can poll it after the connection to the last chunk request was closed early. */
    private static function writeStatus(string $uploadId, array $result): void
    {
        $dir = self::chunkDir($uploadId);
        file_put_contents($dir . '/status.json', json_encode(array_merge(['done' => true], $result)));
    }

    /** @return array|null null means "still processing" (or unknown/expired upload id) */
    public static function readStatus(string $uploadId): ?array
    {
        $path = rtrim((string) Config::get('data_dir'), '/') . '/uploads_tmp/' . $uploadId . '/status.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /** Removes abandoned chunk sessions (e.g. from closed tabs) older than $maxAgeSeconds. */
    public static function cleanupStaleChunkSessions(int $maxAgeSeconds = 86400): void
    {
        $base = rtrim((string) Config::get('data_dir'), '/') . '/uploads_tmp';
        if (!is_dir($base)) {
            return;
        }
        foreach (scandir($base) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $base . '/' . $name;
            if (is_dir($path) && (time() - (int) filemtime($path)) > $maxAgeSeconds) {
                self::deleteDirRecursive($path);
            }
        }
    }

    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    public static function mimeType(string $absPath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $absPath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        return $mime ?: 'application/octet-stream';
    }

    /** Streams a file to the browser, honouring HTTP Range requests, then terminates the script. */
    public static function stream(string $absPath, string $downloadName): never
    {
        $size = (int) filesize($absPath);
        $mime = self::mimeType($absPath);

        $start = 0;
        $end = $size - 1;
        $statusCode = 200;

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = $m[1] === '' ? 0 : (int) $m[1];
            $end   = $m[2] === '' ? $size - 1 : (int) $m[2];
            $end = min($end, $size - 1);
            if ($start > $end || $start < 0) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                exit;
            }
            $statusCode = 206;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('X-Content-Type-Options: nosniff');
        if ($statusCode === 206) {
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        $handle = fopen($absPath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            exit;
        }
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        $chunk = 1024 * 512;
        while ($remaining > 0 && !feof($handle)) {
            $read = (int) min($chunk, $remaining);
            echo fread($handle, $read);
            flush();
            $remaining -= $read;
        }
        fclose($handle);
        exit;
    }
}
