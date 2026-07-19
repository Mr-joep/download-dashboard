<?php

declare(strict_types=1);

/**
 * Minimal session login for the panel. The password lives in config.php,
 * either as plain text or as a password_hash() value.
 */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('downpanel');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['panel_authed']);
    }

    public static function login(string $password, array $config): bool
    {
        $stored = (string) ($config['panel_password'] ?? '');
        if ($stored === '' || $password === '') {
            return false;
        }
        $ok = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2')
            ? password_verify($password, $stored)
            : hash_equals($stored, $password);
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['panel_authed'] = true;
        }
        return $ok;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function validateCsrf(?string $token): void
    {
        if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
            http_response_code(400);
            exit('Invalid CSRF token.');
        }
    }
}
