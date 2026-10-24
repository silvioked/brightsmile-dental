<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Secure Singleton Database Connection using PDO.
 * Supports both Local XAMPP (MySQL) and Supabase / Vercel (PostgreSQL).
 */
final class Database
{
    private static ?PDO $instance = null;

    // Local XAMPP Defaults (Used automatically when running on localhost)
    private const DB_HOST    = '127.0.0.1';
    private const DB_PORT    = '3306';
    private const DB_NAME    = 'dental_clinic_db';
    private const DB_USER    = 'root';
    private const DB_PASS    = '';
    private const DB_CHARSET = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    /**
     * Read environment variable reliably across CLI, Apache, and Vercel PHP-FPM ($_ENV / $_SERVER / getenv).
     */
    private static function env(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        $val = getenv($key);
        return ($val !== false && $val !== '') ? (string) $val : $default;
    }

    /**
     * Parse a PostgreSQL URI safely even when the password contains special characters (@, #, !, brackets, etc.).
     */
    private static function parsePostgresUri(string $uri): ?array
    {
        $uri = trim($uri);
        $cleaned = preg_replace('#^(postgres(?:ql)?://)#i', '', $uri);
        if ($cleaned === null || $cleaned === $uri) {
            return null;
        }

        // Split credentials from host/path at the LAST '@' symbol
        $atPos = strrpos($cleaned, '@');
        if ($atPos === false) {
            return null;
        }

        $credentials = substr($cleaned, 0, $atPos);
        $hostAndPath = substr($cleaned, $atPos + 1);

        $colonPos = strpos($credentials, ':');
        if ($colonPos !== false) {
            $user = rawurldecode(substr($credentials, 0, $colonPos));
            $pass = rawurldecode(substr($credentials, $colonPos + 1));
            // Strip accidental [ ] brackets if user pasted [YOUR-PASSWORD] with brackets around their password
            if (str_starts_with($pass, '[') && str_ends_with($pass, ']')) {
                $pass = substr($pass, 1, -1);
            }
        } else {
            $user = rawurldecode($credentials);
            $pass = '';
        }

        // Remove query string if present
        $qPos = strpos($hostAndPath, '?');
        if ($qPos !== false) {
            $hostAndPath = substr($hostAndPath, 0, $qPos);
        }

        $slashPos = strpos($hostAndPath, '/');
        if ($slashPos !== false) {
            $hostPort = substr($hostAndPath, 0, $slashPos);
            $dbname   = substr($hostAndPath, $slashPos + 1) ?: 'postgres';
        } else {
            $hostPort = $hostAndPath;
            $dbname   = 'postgres';
        }

        $portPos = strrpos($hostPort, ':');
        if ($portPos !== false) {
            $host = substr($hostPort, 0, $portPos);
            $port = substr($hostPort, $portPos + 1) ?: '5432';
        } else {
            $host = $hostPort;
            $port = '5432';
        }

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'name' => $dbname,
        ];
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $databaseUrl = self::env('DATABASE_URL')
                ?: self::env('POSTGRES_URL')
                ?: self::env('SUPABASE_DB_URL');

            $driver = self::env('DB_DRIVER');
            $host   = self::env('DB_HOST') ?: self::env('POSTGRES_HOST', self::DB_HOST);
            $port   = self::env('DB_PORT');
            $name   = self::env('DB_NAME') ?: self::env('POSTGRES_DATABASE', self::DB_NAME);
            $user   = self::env('DB_USER') ?: self::env('POSTGRES_USER', self::DB_USER);
            $pass   = self::env('DB_PASS') !== ''
                ? self::env('DB_PASS')
                : self::env('POSTGRES_PASSWORD', self::DB_PASS);

            if ($databaseUrl !== '') {
                $parsed = self::parsePostgresUri($databaseUrl);
                if ($parsed !== null) {
                    $driver = 'pgsql';
                    $host   = $parsed['host'];
                    $port   = $parsed['port'];
                    $user   = $parsed['user'];
                    $pass   = $parsed['pass'];
                    $name   = $parsed['name'];
                }
            } elseif ($driver === '' && (str_contains($host, 'supabase.co') || str_contains($host, 'supabase.com'))) {
                $driver = 'pgsql';
            } elseif ($driver === '') {
                $driver = 'mysql';
            }

            if ($driver === 'pgsql') {
                $port = $port !== '' ? $port : '5432';
                $dsn  = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $host, $port, $name);
            } else {
                $port = $port !== '' ? $port : self::DB_PORT;
                $dsn  = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $host,
                    $port,
                    $name,
                    self::DB_CHARSET
                );
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Enable emulated prepares on PgBouncer/Supabase pooler so both port 5432 and 6543 work reliably
                PDO::ATTR_EMULATE_PREPARES   => ($driver === 'pgsql'),
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log('[Database Connection Error]: ' . $e->getMessage());
                http_response_code(500);
                $hint = ($databaseUrl === '' && !in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1'], true))
                    ? ' (DATABASE_URL environment variable is not set in Vercel Settings -> Environment Variables)'
                    : ' (' . $e->getMessage() . ')';
                throw new RuntimeException('Database connection error' . $hint);
            }
        }

        return self::$instance;
    }
}
