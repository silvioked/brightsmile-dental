<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Models/Appointment.php';

use App\Core\AuthMiddleware;
use App\Models\Appointment;

header('Content-Type: application/json; charset=UTF-8');

// Require authenticated user (Client, Receptionist, or Admin can check available slots)
AuthMiddleware::requireRole([
    AuthMiddleware::ROLE_CLIENT,
    AuthMiddleware::ROLE_RECEPTIONIST,
    AuthMiddleware::ROLE_ADMIN,
]);

$dentistId = filter_input(INPUT_GET, 'dentist_id', FILTER_VALIDATE_INT);
$serviceId = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);
$date      = trim((string) ($_GET['date'] ?? ''));

if (!$dentistId || !$serviceId || $date === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please select a dental service, dentist, and date.',
        'slots'   => [],
    ]);
    exit;
}

try {
    $appointmentModel = new Appointment();
    $slots = $appointmentModel->getAvailableSlots($dentistId, $serviceId, $date);

    echo json_encode([
        'success' => true,
        'slots'   => $slots,
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'slots'   => [],
    ]);
}
