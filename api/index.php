<?php
declare(strict_types=1);

/**
 * Vercel Serverless Front-Controller Entry Point (api/index.php).
 * Routes incoming requests on Vercel to the corresponding PHP script inside public/.
 */
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

// Whitelist of valid PHP entry points inside public/
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
    // Update SCRIPT_NAME so relative path helpers know the active route
    $_SERVER['SCRIPT_NAME'] = $uriPath;
    chdir(dirname($allowedRoutes[$uriPath]));
    require $allowedRoutes[$uriPath];
    exit;
}

http_response_code(404);
echo '404 Not Found';
