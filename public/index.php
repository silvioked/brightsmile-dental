<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Core/Security.php';
require_once __DIR__ . '/../src/Core/AuthMiddleware.php';

use App\Core\AuthMiddleware;

AuthMiddleware::redirectIfAuthenticated();
header('Location: ' . \App\Core\Security::url('/login.php'));
exit;
