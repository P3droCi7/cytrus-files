<?php
declare(strict_types=1);

namespace App;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Verifies the CSRF token for the current POST request, redirecting back on failure. */
    public static function verifyRequest(string $redirectPage = 'files', array $redirectParams = []): void
    {
        $token = $_POST['_csrf'] ?? null;
        if (!self::check($token)) {
            http_response_code(419);
            flash('error', 'Sesja wygasła lub token CSRF jest nieprawidłowy. Spróbuj ponownie.');
            redirect($redirectPage, $redirectParams);
        }
    }
}
