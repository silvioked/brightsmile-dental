<?php
declare(strict_types=1);

/**
 * Hardened Vercel Serverless Front-Controller Entry Point (api/index.php).
 * - Enforces HTTP method whitelisting
 * - Blocks CGI / PHP2Shell (CVE-2026-109618 / CVE-2026-109619 / CVE-2024-4577) payload patterns
 * - Sets strict HTTP security headers and strips X-Powered-By
 * - Routes strictly to whitelisted PHP scripts inside public/
 */

// 1. Hide PHP version disclosure & set security headers
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// 2. Enforce strict HTTP method whitelist
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
    http_response_code(405);
    exit('405 Method Not Allowed');
}

// 3. Block CGI / PHP2Shell / INI injection vectors in raw URI or query string
$rawRequest = rawurldecode(($method === 'POST' ? '' : '') . ($_SERVER['REQUEST_URI'] ?? '') . '&' . ($_SERVER['QUERY_STRING'] ?? ''));
$blockedPatterns = [
    'allow_url_include',
    'auto_prepend_file',
    'auto_append_file',
    'php://input',
    'php://filter',
    'data://',
    'expect://',
    "\x00",
    "\xAD", // Windows Best-Fit soft-hyphen CGI injection vector
];
foreach ($blockedPatterns as $pattern) {
    if (stripos($rawRequest, $pattern) !== false) {
        http_response_code(403);
        exit('403 Forbidden');
    }
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uriPath = '/' . ltrim($uriPath, '/');

// Strip optional '/public' prefix if present
if (str_starts_with($uriPath, '/public/')) {
    $uriPath = substr($uriPath, 7);
}

// Default root '/' to '/login.php'
if ($uriPath === '/' || $uriPath === '/index.php') {
    $uriPath = '/login.php';
}

// 4. Strict whitelist of valid PHP entry points inside public/
$allowedRoutes = [
    '/login.php'                     => __DIR__ . '/../public/login.php',
    '/register.php'                  => __DIR__ . '/../public/register.php',
    '/logout.php'                    => __DIR__ . '/../public/logout.php',
    '/api/slots.php'                 => __DIR__ . '/../public/api/slots.php',
    '/client/book.php'               => __DIR__ . '/../public/client/book.php',
    '/receptionist/appointments.php' => __DIR__ . '/../public/receptionist/appointments.php',
    '/dentist/schedule.php'          => __DIR__ . '/../public/dentist/schedule.php',
    '/admin/dashboard.php'           => __DIR__ . '/../public/admin/dashboard.php',
];

if (isset($allowedRoutes[$uriPath]) && is_file($allowedRoutes[$uriPath])) {
    $_SERVER['SCRIPT_NAME'] = $uriPath;
    chdir(dirname($allowedRoutes[$uriPath]));
    require $allowedRoutes[$uriPath];
    exit;
}

http_response_code(404);
echo '404 Not Found';
