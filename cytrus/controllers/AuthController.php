<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;

use function App\flash;
use function App\redirect;
use function App\view;

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            redirect('files');
        }
        view('login', [], false);
    }

    public static function login(): void
    {
        Csrf::verifyRequest('login');

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            flash('error', 'Podaj login i hasło.');
            redirect('login');
        }

        $result = Auth::attempt($username, $password);

        if (!$result['ok']) {
            if (($result['reason'] ?? '') === 'locked') {
                $wait = $result['wait'] ?? 0;
                flash('error', "Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za {$wait} s.");
            } else {
                flash('error', 'Nieprawidłowy login lub hasło.');
            }
            redirect('login');
        }

        redirect('files');
    }

    public static function logout(): void
    {
        Csrf::verifyRequest('files');
        Auth::logout();
        redirect('login');
    }
}
