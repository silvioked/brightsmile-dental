<?php
declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use PDO;

final class AuthMiddleware
{
    public const ROLE_ADMIN        = 'admin';
    public const ROLE_DENTIST      = 'dentist';
    public const ROLE_RECEPTIONIST = 'receptionist';
    public const ROLE_CLIENT       = 'client';

    private const SESSION_IDLE_TIMEOUT = 1800; // 30 minutes

    private const ROLE_DASHBOARDS = [
        self::ROLE_ADMIN        => '/admin/dashboard.php',
        self::ROLE_DENTIST      => '/dentist/schedule.php',
        self::ROLE_RECEPTIONIST => '/receptionist/appointments.php',
        self::ROLE_CLIENT       => '/client/book.php',
    ];

    /**
     * Authenticate a user with email & password, mitigating brute-force and session fixation.
     */
    public static function attemptLogin(string $email, string $password): array
    {
        Security::initSecureSession();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT u.id, u.first_name, u.last_name, u.email, u.password_hash,
                   u.is_active, u.failed_login_attempts, u.locked_until,
                   r.slug AS role_slug
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.email = :email
            LIMIT 1
        ');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $genericError = ['success' => false, 'message' => 'Invalid email or password.'];

        if (!$user) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return $genericError;
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account temporarily locked due to multiple failed attempts. Please try again later.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($pdo, (int) $user['id'], (int) $user['failed_login_attempts']);
            return $genericError;
        }

        if ((int) $user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Your account has been deactivated. Please contact the clinic administrator.'];
        }

        $resetStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $resetStmt->execute([':id' => $user['id']]);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'name'      => $user['first_name'] . ' ' . $user['last_name'],
            'email'     => $user['email'],
            'role'      => $user['role_slug'],
        ];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['csrf_token']);
        Security::generateCsrfToken();
        Security::syncServerlessState();

        return [
            'success'  => true,
            'redirect' => self::getDashboardUrl($user['role_slug']),
        ];
    }

    /**
     * Protect a route by requiring authentication and checking allowed roles.
     *
     * @param array<string> $allowedRoles
     */
    public static function requireRole(array $allowedRoles): void
    {
        Security::initSecureSession();

        if (empty($_SESSION['user']['id']) || empty($_SESSION['user']['role'])) {
            self::redirect('/login.php?error=auth_required');
        }

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::SESSION_IDLE_TIMEOUT) {
            self::logout();
            self::redirect('/login.php?error=session_expired');
        }
        $_SESSION['last_activity'] = time();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT u.is_active, r.slug AS role_slug
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $_SESSION['user']['id']]);
        $liveUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$liveUser || (int) $liveUser['is_active'] !== 1) {
            self::logout();
            self::redirect('/login.php?error=account_disabled');
        }

        $_SESSION['user']['role'] = $liveUser['role_slug'];

        if (!in_array($liveUser['role_slug'], $allowedRoles, true)) {
            http_response_code(403);
            $safeDashboard = self::getDashboardUrl($liveUser['role_slug']);
            self::redirect($safeDashboard . '?error=unauthorized_access');
        }
    }

    /**
     * Enforce CSRF verification on state-changing requests.
     */
    public static function requireCsrfOnPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!Security::verifyCsrfToken($token)) {
                http_response_code(419);
                die('Security Error: Invalid or expired CSRF token. Please refresh the page and try again.');
            }
        }
    }

    public static function redirectIfAuthenticated(): void
    {
        Security::initSecureSession();
        if (!empty($_SESSION['user']['role'])) {
            self::redirect(self::getDashboardUrl($_SESSION['user']['role']));
        }
    }

    public static function getDashboardUrl(string $roleSlug): string
    {
        $path = self::ROLE_DASHBOARDS[$roleSlug] ?? '/login.php';
        return Security::url($path);
    }

    public static function logout(): void
    {
        Security::initSecureSession();
        $_SESSION = [];
        Security::syncServerlessState();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    private static function recordFailedAttempt(PDO $pdo, int $userId, int $currentAttempts): void
    {
        $attempts = $currentAttempts + 1;
        $lockedUntil = ($attempts >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;

        $stmt = $pdo->prepare('
            UPDATE users
            SET failed_login_attempts = :attempts, locked_until = :locked_until
            WHERE id = :id
        ');
        $stmt->execute([
            ':attempts'     => $attempts,
            ':locked_until' => $lockedUntil,
            ':id'           => $userId,
        ]);
    }

    private static function redirect(string $url): never
    {
        if (!str_starts_with($url, 'http') && !str_contains($url, '/public/')) {
            $url = Security::url($url);
        }
        header('Location: ' . $url);
        exit;
    }
}
