<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Models/Appointment.php';

use App\Core\AuthMiddleware;
use App\Core\Security;
use App\Models\Appointment;

AuthMiddleware::requireRole([
    AuthMiddleware::ROLE_RECEPTIONIST,
    AuthMiddleware::ROLE_ADMIN,
]);
AuthMiddleware::requireCsrfOnPost();

$appointmentModel = new Appointment();
$currentUser = $_SESSION['user'];
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $newStatus     = trim((string) ($_POST['status'] ?? ''));
        $newDate       = trim((string) ($_POST['reschedule_date'] ?? ''));
        $newTime       = trim((string) ($_POST['reschedule_time'] ?? ''));
        $cancelReason  = trim((string) ($_POST['cancellation_reason'] ?? ''));

        if ($appointmentId && $newStatus !== '') {
            $feedback = $appointmentModel->updateStatusOrReschedule(
                $appointmentId,
                $newStatus,
                (int) $currentUser['id'],
                $newDate ?: null,
                $newTime ?: null,
                $cancelReason ?: null
            );
        }
    } elseif ($action === 'walk_in') {
        $patientId = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $dentistId = filter_input(INPUT_POST, 'dentist_id', FILTER_VALIDATE_INT);
        $date      = trim((string) ($_POST['appointment_date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $notes     = trim((string) ($_POST['patient_notes'] ?? ''));

        if ($patientId && $serviceId && $dentistId && $date !== '' && $startTime !== '') {
            $feedback = $appointmentModel->createAppointment(
                $patientId,
                $dentistId,
                $serviceId,
                $date,
                $startTime,
                'Walk-In',
                $notes,
                (int) $currentUser['id']
            );
        } else {
            $feedback = ['success' => false, 'message' => 'Please complete all required Walk-In fields.'];
        }
    }
}

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$dateFilter   = trim((string) ($_GET['date'] ?? ''));

$appointments = $appointmentModel->getAllAppointments($statusFilter ?: null, $dateFilter ?: null);
$patients     = $appointmentModel->getActivePatients();
$services     = $appointmentModel->getActiveServices();
$dentists     = $appointmentModel->getActiveDentists();

$activeNav = $statusFilter === 'Pending' ? 'pending' : ($statusFilter === 'Confirmed' ? 'confirmed' : 'queue');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Desk — BrightSmile Dental Clinic</title>
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
        <header class="sticky top-0 z-20 bg-white/85 backdrop-blur-md border-b border-slate-200/80 px-4 sm:px-8 py-4 flex justify-between items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" onclick="toggleSidebar(true)" aria-label="Open menu"
                        class="lg:hidden w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-lg flex-shrink-0">
                    ☰
                </button>
                <div class="truncate">
                    <h1 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight truncate">Front-Desk Operations & Queue</h1>
                    <p class="text-xs text-slate-500 hidden sm:block">Approve pending requests, reschedule slots, and register walk-in patients</p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                <span class="hidden sm:inline-flex items-center gap-2 text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 px-3.5 py-1.5 rounded-full">
                    <span>📅</span>
                    <span><?= date('l, M j, Y') ?></span>
                </span>
                <a href="<?= Security::escape(Security::url('/logout.php')) ?>"
                   class="lg:hidden text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-2 rounded-xl">
                    Sign Out
                </a>
            </div>
        </header>

        <main class="p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
            <?php if ($feedback !== null): ?>
                <div class="p-4 rounded-2xl border flex items-center gap-3 text-sm font-medium <?= $feedback['success'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                    <span class="text-lg"><?= $feedback['success'] ? '✓' : '⚠' ?></span>
                    <span><?= Security::escape($feedback['message']) ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                <!-- Left Column: Appointments Queue -->
                <section id="queue" class="xl:col-span-8 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80">
                    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">Appointment Queue</h2>
                            <p class="text-xs text-slate-500">Showing <?= count($appointments) ?> matching booking(s)</p>
                        </div>
                        <form method="GET" class="flex flex-wrap items-center gap-2 text-xs">
                            <select name="status" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-semibold">
                                <option value="">All Statuses</option>
                                <?php foreach (['Pending', 'Confirmed', 'Rescheduled', 'Completed', 'Cancelled'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date" value="<?= Security::escape($dateFilter) ?>"
                                   class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-semibold">
                            <button type="submit" class="bg-slate-900 text-white font-bold px-4 py-2 rounded-xl hover:bg-slate-800 transition">
                                Filter
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-3.5 px-3">Ref / Channel</th>
                                    <th class="py-3.5 px-3">Patient</th>
                                    <th class="py-3.5 px-3">Procedure & Dentist</th>
                                    <th class="py-3.5 px-3">Date & Slot</th>
                                    <th class="py-3.5 px-3">Status</th>
                                    <th class="py-3.5 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($appointments)): ?>
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-slate-400">No appointments match the current filter.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($appointments as $apt): ?>
                                    <?php
                                    $badgeColors = [
                                        'Pending'     => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'Confirmed'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Rescheduled' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'Completed'   => 'bg-slate-100 text-slate-700 border-slate-300',
                                        'Cancelled'   => 'bg-rose-50 text-rose-700 border-rose-200',
                                    ];
                                    $badgeClass = $badgeColors[$apt['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                                    ?>
                                    <tr class="hover:bg-slate-50/80 align-top transition">
                                        <td class="py-4 px-3">
                                            <div class="font-mono text-xs font-bold text-slate-700"><?= Security::escape($apt['reference_code']) ?></div>
                                            <span class="inline-block mt-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-slate-100 text-slate-600">
                                                <?= Security::escape($apt['booking_type']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-3">
                                            <div class="font-bold text-slate-900"><?= Security::escape($apt['patient_name']) ?></div>
                                            <div class="text-xs text-slate-500"><?= Security::escape($apt['patient_phone']) ?></div>
                                        </td>
                                        <td class="py-4 px-3">
                                            <div class="font-semibold text-slate-800"><?= Security::escape($apt['service_name']) ?></div>
                                            <div class="text-xs font-semibold text-teal-700"><?= Security::escape($apt['dentist_name']) ?></div>
                                        </td>
                                        <td class="py-4 px-3 text-xs">
                                            <div class="font-bold text-slate-800"><?= Security::escape(date('M j, Y', strtotime($apt['appointment_date']))) ?></div>
                                            <div class="text-slate-500">
                                                <?= Security::escape(date('g:i A', strtotime($apt['start_time']))) ?> –
                                                <?= Security::escape(date('g:i A', strtotime($apt['end_time']))) ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-3">
                                            <span class="text-xs font-bold px-2.5 py-1 rounded-full border <?= $badgeClass ?>">
                                                <?= Security::escape($apt['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-3 text-right">
                                            <form method="POST" class="inline-flex flex-col items-end gap-1.5">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= (int) $apt['id'] ?>">

                                                <div class="flex gap-1.5">
                                                    <?php if ($apt['status'] === 'Pending'): ?>
                                                        <button type="submit" name="status" value="Confirmed"
                                                                class="bg-emerald-600 text-white text-xs font-bold px-3 py-1.5 rounded-xl hover:bg-emerald-700 shadow-sm transition">
                                                            ✓ Approve
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($apt['status'] !== 'Cancelled' && $apt['status'] !== 'Completed'): ?>
                                                        <button type="submit" name="status" value="Cancelled"
                                                                class="bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold px-3 py-1.5 rounded-xl hover:bg-rose-100 transition">
                                                            Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($apt['status'] !== 'Cancelled' && $apt['status'] !== 'Completed'): ?>
                                                    <details class="text-xs mt-1 text-left">
                                                        <summary class="cursor-pointer text-indigo-600 font-bold hover:underline text-right">Reschedule</summary>
                                                        <div class="mt-2 flex flex-col gap-2 p-3 bg-slate-50 border border-slate-200 rounded-2xl shadow-sm">
                                                            <input type="date" name="reschedule_date" min="<?= date('Y-m-d') ?>"
                                                                   value="<?= Security::escape($apt['appointment_date']) ?>"
                                                                   class="border border-slate-200 rounded-lg px-2 py-1 text-xs">
                                                            <input type="time" name="reschedule_time"
                                                                   value="<?= Security::escape(substr($apt['start_time'], 0, 5)) ?>"
                                                                   class="border border-slate-200 rounded-lg px-2 py-1 text-xs">
                                                            <button type="submit" name="status" value="Rescheduled"
                                                                    class="bg-indigo-600 text-white font-bold text-xs py-1.5 rounded-lg hover:bg-indigo-700">
                                                                Save New Slot
                                                            </button>
                                                        </div>
                                                    </details>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Right Column: Walk-In Registration -->
                <section id="walkin" class="xl:col-span-4 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 h-fit">
                    <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full mb-3">
                        Instant Walk-In
                    </div>
                    <h2 class="text-lg font-extrabold text-slate-900">Register Walk-In Patient</h2>
                    <p class="text-xs text-slate-500 mt-0.5 mb-5">Locks a confirmed time slot with automatic conflict checking.</p>

                    <form method="POST" class="space-y-4 text-sm">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="walk_in">

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Registered Patient</label>
                            <select name="patient_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5">
                                <option value="">-- Select Patient --</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>">
                                        <?= Security::escape($p['last_name'] . ', ' . $p['first_name'] . ' (' . $p['phone'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Dental Procedure</label>
                            <select name="service_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5">
                                <option value="">-- Select Service --</option>
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?= (int) $svc['id'] ?>">
                                        <?= Security::escape($svc['name']) ?> (<?= (int) $svc['duration_minutes'] ?>m)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Assign Dentist</label>
                            <select name="dentist_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5">
                                <option value="">-- Select Dentist --</option>
                                <?php foreach ($dentists as $doc): ?>
                                    <option value="<?= (int) $doc['id'] ?>">
                                        Dr. <?= Security::escape($doc['first_name'] . ' ' . $doc['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Date</label>
                                <input type="date" name="appointment_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Start Time</label>
                                <input type="time" name="start_time" value="09:00" required
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Walk-In Notes</label>
                            <input type="text" name="patient_notes" maxlength="500" placeholder="Chief complaint or notes..."
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5">
                        </div>

                        <button type="submit"
                                class="w-full bg-gradient-to-r from-indigo-600 to-teal-600 hover:from-indigo-500 hover:to-teal-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-indigo-600/20 transition">
                            Confirm Walk-In Slot →
                        </button>
                    </form>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
