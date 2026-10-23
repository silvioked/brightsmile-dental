<?php
declare(strict_types=1);

namespace App\Core;

final class Security
{
    private const AUTH_COOKIE_NAME = 'bs_auth_state';

    /**
     * Dynamically resolve the public base URL path so the app works across:
     * - Vercel root domain (https://your-app.vercel.app)
     * - XAMPP subfolder (http://localhost/dental-clinic-system/public)
     */
    public static function url(string $path = ''): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        // Only prefix '/dental-clinic-system/public' when the browser URL itself uses '/public/'
        $publicPos = strpos($scriptName, '/public/');
        if ($publicPos !== false && str_contains($requestUri, '/public/')) {
            $base = substr($scriptName, 0, $publicPos + 7);
        } else {
            $base = '';
        }

        $normalizedPath = '/' . ltrim($path, '/');
        return $base . $normalizedPath;
    }

    /**
     * Start a hardened PHP session and restore signed serverless cookie state if on Vercel.
     */
    public static function initSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }

        // Restore session state from HMAC-signed cookie on stateless serverless hosts (Vercel)
        if (empty($_SESSION['user']) && !empty($_COOKIE[self::AUTH_COOKIE_NAME])) {
            $restored = self::verifySignedState((string) $_COOKIE[self::AUTH_COOKIE_NAME]);
            if (is_array($restored) && !empty($restored['user'])) {
                $_SESSION['user']          = $restored['user'];
                $_SESSION['last_activity'] = $restored['last_activity'] ?? time();
                $_SESSION['csrf_token']    = $restored['csrf_token'] ?? null;
            }
        }
    }

    /**
     * Persist authenticated session state in an HMAC-SHA256 signed HttpOnly cookie for Vercel Serverless.
     */
    public static function syncServerlessState(): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        if (empty($_SESSION['user'])) {
            setcookie(self::AUTH_COOKIE_NAME, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        $payload = json_encode([
            'user'          => $_SESSION['user'],
            'last_activity' => $_SESSION['last_activity'] ?? time(),
            'csrf_token'    => self::generateCsrfToken(),
        ]);

        $encoded = base64_encode((string) $payload);
        $sig     = hash_hmac('sha256', $encoded, self::getAppSecret());

        setcookie(self::AUTH_COOKIE_NAME, $encoded . '.' . $sig, [
            'expires'  => time() + 1800,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Generate or retrieve a deterministic/session CSRF token.
     */
    public static function generateCsrfToken(): string
    {
        self::initSecureSession();
        if (empty($_SESSION['csrf_token'])) {
            $seed = ($_SESSION['user']['id'] ?? session_id()) . '|' . date('Y-m-d');
            $_SESSION['csrf_token'] = hash_hmac('sha256', $seed, self::getAppSecret());
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Render a hidden CSRF input field for HTML forms.
     */
    public static function csrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . self::escape($token) . '">';
    }

    /**
     * Validate a submitted CSRF token using timing-attack-safe comparison.
     */
    public static function verifyCsrfToken(?string $submittedToken): bool
    {
        self::initSecureSession();
        if (empty($submittedToken)) {
            return false;
        }
        $expected = self::generateCsrfToken();
        return hash_equals($expected, $submittedToken);
    }

    /**
     * Escape output to prevent Cross-Site Scripting (XSS).
     */
    public static function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Hash a password using Argon2id (falls back to Bcrypt if Argon2id isn't available).
     */
    public static function hashPassword(string $plainPassword): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($plainPassword, $algo);
    }

    private static function getAppSecret(): string
    {
        return getenv('APP_SECRET') ?: (getenv('POSTGRES_PASSWORD') ?: 'brightsmile-default-hmac-secret-key-2026');
    }

    private static function verifySignedState(string $cookieValue): ?array
    {
        $parts = explode('.', $cookieValue, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $sig] = $parts;
        $expectedSig = hash_hmac('sha256', $encoded, self::getAppSecret());
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }
        $decoded = json_decode((string) base64_decode($encoded, true), true);
        return is_array($decoded) ? $decoded : null;
    }
}
