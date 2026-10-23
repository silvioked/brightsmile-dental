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

    // Local XAMPP Defaults (Used automatically when no Supabase env vars are set)
    private const DB_HOST    = '127.0.0.1';
    private const DB_PORT    = '3306';
    private const DB_NAME    = 'dental_clinic_db';
    private const DB_USER    = 'root';
    private const DB_PASS    = '';
    private const DB_CHARSET = 'utf8mb4';

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            // 1. Check if a full Supabase / Vercel Postgres URI is provided (e.g. POSTGRES_URL or DATABASE_URL)
            $databaseUrl = getenv('POSTGRES_URL') ?: getenv('DATABASE_URL') ?: getenv('SUPABASE_DB_URL');

            $driver = getenv('DB_DRIVER') ?: '';
            $host   = getenv('DB_HOST') ?: getenv('POSTGRES_HOST') ?: self::DB_HOST;
            $port   = getenv('DB_PORT') ?: '';
            $name   = getenv('DB_NAME') ?: getenv('POSTGRES_DATABASE') ?: self::DB_NAME;
            $user   = getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: self::DB_USER;
            $pass   = getenv('DB_PASS') !== false
                ? getenv('DB_PASS')
                : (getenv('POSTGRES_PASSWORD') !== false ? getenv('POSTGRES_PASSWORD') : self::DB_PASS);

            if ($databaseUrl) {
                $parts  = parse_url($databaseUrl);
                $driver = 'pgsql';
                $host   = $parts['host'] ?? $host;
                $port   = isset($parts['port']) ? (string) $parts['port'] : '5432';
                $user   = isset($parts['user']) ? urldecode($parts['user']) : $user;
                $pass   = isset($parts['pass']) ? urldecode($parts['pass']) : $pass;
                $name   = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
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
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log('[Database Connection Error]: ' . $e->getMessage());
                http_response_code(500);
                throw new RuntimeException('A system error occurred while connecting to the database.');
            }
        }

        return self::$instance;
    }
}
