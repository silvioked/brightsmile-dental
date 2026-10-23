<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/AuthMiddleware.php';

use App\Config\Database;
use App\Core\AuthMiddleware;
use App\Core\Security;

AuthMiddleware::requireRole([AuthMiddleware::ROLE_ADMIN]);
AuthMiddleware::requireCsrfOnPost();

$pdo = Database::getConnection();
$currentUser = $_SESSION['user'];
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_service') {
        $name     = trim((string) ($_POST['name'] ?? ''));
        $desc     = trim((string) ($_POST['description'] ?? ''));
        $duration = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
        $price    = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

        if ($name !== '' && $duration && $duration >= 15 && $price !== false && $price >= 0) {
            $stmt = $pdo->prepare('
                INSERT INTO services (name, description, duration_minutes, price)
                VALUES (:name, :description, :duration, :price)
            ');
            $stmt->execute([
                ':name'        => $name,
                ':description' => $desc ?: null,
                ':duration'    => $duration,
                ':price'       => $price,
            ]);
            $feedback = ['success' => true, 'message' => "Service '{$name}' added successfully."];
        } else {
            $feedback = ['success' => false, 'message' => 'Please provide valid service name, duration (>=15m), and price.'];
        }
    } elseif ($action === 'add_staff') {
        $roleId    = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName  = trim((string) ($_POST['last_name'] ?? ''));
        $email     = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone     = trim((string) ($_POST['phone'] ?? ''));
        $password  = (string) ($_POST['password'] ?? '');
        $license   = trim((string) ($_POST['license_number'] ?? ''));
        $spec      = trim((string) ($_POST['specialization'] ?? ''));

        if ($roleId && in_array($roleId, [2, 3], true) && $firstName !== '' && $lastName !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 8) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('
                    INSERT INTO users (role_id, first_name, last_name, email, phone, password_hash, license_number, specialization)
                    VALUES (:role_id, :first_name, :last_name, :email, :phone, :password_hash, :license, :spec)
                ');
                $stmt->execute([
                    ':role_id'       => $roleId,
                    ':first_name'    => $firstName,
                    ':last_name'     => $lastName,
                    ':email'         => $email,
                    ':phone'         => $phone,
                    ':password_hash' => Security::hashPassword($password),
                    ':license'       => $license ?: null,
                    ':spec'          => $spec ?: null,
                ]);
                $newUserId = (int) $pdo->lastInsertId();

                if ($roleId === 2) {
                    $schedStmt = $pdo->prepare("
                        INSERT INTO dentist_schedules (dentist_id, day_of_week, start_time, end_time, break_start, break_end, is_available)
                        VALUES (:dentist_id, :day, '09:00:00', '17:00:00', '12:00:00', '13:00:00', 1)
                    ");
                    for ($day = 1; $day <= 5; $day++) {
                        $schedStmt->execute([':dentist_id' => $newUserId, ':day' => $day]);
                    }
                }

                $pdo->commit();
                $feedback = ['success' => true, 'message' => "Account for {$firstName} {$lastName} created."];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $feedback = ['success' => false, 'message' => 'Could not create account (Email may already be in use).'];
            }
        } else {
            $feedback = ['success' => false, 'message' => 'Please complete all required staff fields (password min 8 chars).'];
        }
    }
}

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM appointments
")->fetch();

$revenue = $pdo->query("
    SELECT COALESCE(SUM(s.price), 0) AS total_revenue
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.id
    WHERE a.status = 'Completed'
")->fetchColumn();

$services = $pdo->query('SELECT * FROM services ORDER BY name ASC')->fetchAll();
$staffList = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.specialization, u.is_active, r.name AS role_name
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE r.slug IN ('admin', 'dentist', 'receptionist')
    ORDER BY r.id ASC, u.last_name ASC
")->fetchAll();
$activeNav = 'overview';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console — BrightSmile Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100/80 h-screen overflow-hidden text-slate-800 flex">
    <!-- Left Sidebar -->
    <?php require __DIR__ . '/../../views/layouts/sidebar.php'; ?>

    <!-- Main Workspace Area (Scrolls independently while sidebar stays locked) -->
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto scroll-smooth">
        <!-- Top Workspace Header -->
        <header class="sticky top-0 z-20 bg-white/85 backdrop-blur-md border-b border-slate-200/80 px-6 sm:px-8 py-4 flex justify-between items-center">
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Executive Clinic Administration</h1>
                <p class="text-xs text-slate-500">Analytics, procedure catalog pricing, and staff account provisioning</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-2 text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200 px-3.5 py-1.5 rounded-full">
                    <span>⚡</span>
                    <span><?= date('l, M j, Y') ?></span>
                </span>
                <a href="<?= Security::escape(Security::url('/logout.php')) ?>"
                   class="lg:hidden text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl">
                    Sign Out
                </a>
            </div>
        </header>

        <main class="p-6 sm:p-8 max-w-7xl w-full mx-auto space-y-8">
            <?php if ($feedback !== null): ?>
                <div class="p-4 rounded-2xl border flex items-center gap-3 text-sm font-medium <?= $feedback['success'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                    <span class="text-lg"><?= $feedback['success'] ? '✓' : '⚠' ?></span>
                    <span><?= Security::escape($feedback['message']) ?></span>
                </div>
            <?php endif; ?>

            <!-- KPI Metric Cards -->
            <section id="overview" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-400 uppercase font-extrabold tracking-wider">Pending Requests</div>
                        <div class="text-3xl font-extrabold text-amber-600 mt-1"><?= (int) ($stats['pending_count'] ?? 0) ?></div>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">⏳</span>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-400 uppercase font-extrabold tracking-wider">Confirmed Visits</div>
                        <div class="text-3xl font-extrabold text-teal-600 mt-1"><?= (int) ($stats['confirmed_count'] ?? 0) ?></div>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl font-bold">📅</span>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-400 uppercase font-extrabold tracking-wider">Completed Visits</div>
                        <div class="text-3xl font-extrabold text-slate-900 mt-1"><?= (int) ($stats['completed_count'] ?? 0) ?></div>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center text-xl font-bold">✓</span>
                </div>

                <div class="bg-gradient-to-br from-teal-600 to-cyan-700 text-white p-6 rounded-3xl shadow-lg shadow-teal-600/20 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-teal-100 uppercase font-extrabold tracking-wider">Completed Revenue</div>
                        <div class="text-3xl font-extrabold mt-1">₱<?= number_format((float) $revenue, 2) ?></div>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center text-xl font-bold">₱</span>
                </div>
            </section>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                <!-- Manage Dental Services -->
                <section id="services" class="xl:col-span-6 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Dental Services & Pricing</h2>
                        <p class="text-xs text-slate-500">Configure procedures, durations, and standard clinic rates</p>
                    </div>

                    <form method="POST" class="space-y-3 bg-slate-50 p-5 rounded-2xl border border-slate-200/80 text-sm">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="add_service">
                        <div class="text-xs font-extrabold uppercase tracking-wider text-teal-700">+ Add Dental Procedure</div>
                        <input type="text" name="name" placeholder="Procedure Name (e.g., Panoramic Dental X-Ray)" required
                               class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        <div class="grid grid-cols-2 gap-3">
                            <input type="number" name="duration_minutes" placeholder="Duration (mins)" min="15" step="15" required
                                   class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                            <input type="number" name="price" placeholder="Rate (PHP)" min="0" step="0.01" required
                                   class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        </div>
                        <input type="text" name="description" placeholder="Short clinical description"
                               class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        <button type="submit" class="bg-teal-600 text-white font-bold px-5 py-2.5 rounded-xl hover:bg-teal-700 shadow-sm transition">
                            Save Procedure
                        </button>
                    </form>

                    <div class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($services as $svc): ?>
                            <div class="py-3 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-slate-800"><?= Security::escape($svc['name']) ?></div>
                                    <div class="text-xs text-slate-500">⏱ <?= (int) $svc['duration_minutes'] ?> mins slot duration</div>
                                </div>
                                <div class="font-extrabold text-teal-700 bg-teal-50 px-3 py-1 rounded-xl">
                                    ₱<?= number_format((float) $svc['price'], 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Manage Clinic Staff & Dentists -->
                <section id="staff" class="xl:col-span-6 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-sm space-y-6">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Clinic Staff & Dentist Directory</h2>
                        <p class="text-xs text-slate-500">Provision accounts with automatic Mon–Fri dentist schedules</p>
                    </div>

                    <form method="POST" class="space-y-3 bg-slate-50 p-5 rounded-2xl border border-slate-200/80 text-sm">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="add_staff">
                        <div class="text-xs font-extrabold uppercase tracking-wider text-indigo-700">+ Provision Staff / Dentist Account</div>
                        <div class="grid grid-cols-2 gap-3">
                            <select name="role_id" required class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 font-semibold">
                                <option value="2">🦷 Dentist</option>
                                <option value="3">🗂 Receptionist / Staff</option>
                            </select>
                            <input type="text" name="phone" placeholder="Mobile Phone" required class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="first_name" placeholder="First Name" required class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                            <input type="text" name="last_name" placeholder="Last Name" required class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="email" name="email" placeholder="Email Address" required class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                            <input type="password" name="password" placeholder="Password (min 8 chars)" minlength="8" required class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="license_number" placeholder="PRC License (Dentists)" class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                            <input type="text" name="specialization" placeholder="Specialization (Dentists)" class="bg-white border border-slate-200 rounded-xl px-3.5 py-2.5">
                        </div>
                        <button type="submit" class="bg-slate-900 text-white font-bold px-5 py-2.5 rounded-xl hover:bg-slate-800 shadow-sm transition">
                            Create Staff Account
                        </button>
                    </form>

                    <div class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($staffList as $stf): ?>
                            <div class="py-3 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center">
                                        <?= Security::escape(strtoupper(substr($stf['first_name'], 0, 1) . substr($stf['last_name'], 0, 1))) ?>
                                    </span>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= Security::escape($stf['first_name'] . ' ' . $stf['last_name']) ?></div>
                                        <div class="text-xs text-slate-500"><?= Security::escape($stf['email']) ?></div>
                                    </div>
                                </div>
                                <span class="text-xs px-3 py-1 rounded-full bg-slate-100 font-bold text-slate-700">
                                    <?= Security::escape($stf['role_name']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
