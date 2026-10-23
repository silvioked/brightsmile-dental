<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Models/Appointment.php';

use App\Core\AuthMiddleware;
use App\Core\Security;
use App\Models\Appointment;

AuthMiddleware::requireRole([AuthMiddleware::ROLE_CLIENT]);
AuthMiddleware::requireCsrfOnPost();

$appointmentModel = new Appointment();
$currentUser = $_SESSION['user'];
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $dentistId = filter_input(INPUT_POST, 'dentist_id', FILTER_VALIDATE_INT);
    $date      = trim((string) ($_POST['appointment_date'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $notes     = trim((string) ($_POST['patient_notes'] ?? ''));

    if (!$serviceId || !$dentistId || $date === '' || $startTime === '') {
        $feedback = ['success' => false, 'message' => 'All required fields (Service, Dentist, Date, and Time Slot) must be selected.'];
    } else {
        $feedback = $appointmentModel->createAppointment(
            (int) $currentUser['id'],
            $dentistId,
            $serviceId,
            $date,
            $startTime,
            'Online',
            $notes
        );
    }
}

$services = $appointmentModel->getActiveServices();
$dentists = $appointmentModel->getActiveDentists();
$myAppointments = $appointmentModel->getPatientAppointments((int) $currentUser['id']);
$activeNav = 'book';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal — BrightSmile Dental Clinic</title>
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
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Patient Appointment Portal</h1>
                <p class="text-xs text-slate-500">Reserve your dental procedure with real-time slot verification</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-2 text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200 px-3.5 py-1.5 rounded-full">
                    <span>📅</span>
                    <span><?= date('l, M j, Y') ?></span>
                </span>
                <a href="<?= Security::escape(Security::url('/logout.php')) ?>"
                   class="lg:hidden text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl">
                    Sign Out
                </a>
            </div>
        </header>

        <main class="p-6 sm:p-8 max-w-7xl w-full mx-auto grid grid-cols-1 xl:grid-cols-12 gap-8">
            <!-- Left Column: Booking Wizard Card -->
            <section id="book-section" class="xl:col-span-7 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80">
                <div class="mb-6">
                    <span class="text-xs font-bold uppercase tracking-widest text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Online Booking</span>
                    <h2 class="text-xl font-extrabold text-slate-900 mt-2">Reserve an Appointment</h2>
                </div>

                <?php if ($feedback !== null): ?>
                    <div class="mb-6 p-4 rounded-2xl border flex items-center gap-3 text-sm font-medium <?= $feedback['success'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                        <span class="text-lg"><?= $feedback['success'] ? '✓' : '⚠' ?></span>
                        <span><?= Security::escape($feedback['message']) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="bookingForm" class="space-y-6">
                    <?= Security::csrfField() ?>

                    <!-- Step 1: Dental Service -->
                    <div>
                        <label for="service_id" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                            <span class="w-5 h-5 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">1</span>
                            Select Dental Procedure
                        </label>
                        <select id="service_id" name="service_id" required
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                            <option value="">-- Choose a dental service --</option>
                            <?php foreach ($services as $svc): ?>
                                <option value="<?= (int) $svc['id'] ?>">
                                    <?= Security::escape($svc['name']) ?>
                                    (<?= (int) $svc['duration_minutes'] ?> mins • ₱<?= number_format((float) $svc['price'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Step 2 & 3: Dentist & Date -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="dentist_id" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                                <span class="w-5 h-5 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">2</span>
                                Preferred Dentist
                            </label>
                            <select id="dentist_id" name="dentist_id" required
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                                <option value="">-- Choose a dentist --</option>
                                <?php foreach ($dentists as $doc): ?>
                                    <option value="<?= (int) $doc['id'] ?>">
                                        Dr. <?= Security::escape($doc['first_name'] . ' ' . $doc['last_name']) ?>
                                        (<?= Security::escape($doc['specialization'] ?? 'General') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="appointment_date" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                                <span class="w-5 h-5 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">3</span>
                                Preferred Date
                            </label>
                            <input type="date" id="appointment_date" name="appointment_date"
                                   min="<?= date('Y-m-d') ?>" required
                                   class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Step 4: Dynamic Time Slots -->
                    <div>
                        <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                            <span class="w-5 h-5 rounded-full bg-teal-600 text-white text-[11px] flex items-center justify-center font-bold">4</span>
                            Available Time Slots
                        </label>
                        <input type="hidden" name="start_time" id="selected_start_time" required>
                        <div id="slotsContainer" data-api-url="<?= Security::escape(Security::url('/api/slots.php')) ?>"
                             class="p-6 rounded-2xl bg-slate-50 border border-dashed border-slate-300 text-sm text-slate-500 text-center">
                            Select a procedure, dentist, and date above to load available time slots.
                        </div>
                    </div>

                    <!-- Step 5: Notes -->
                    <div>
                        <label for="patient_notes" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                            Chief Complaint / Notes (Optional)
                        </label>
                        <textarea id="patient_notes" name="patient_notes" rows="2" maxlength="500"
                                  placeholder="Any tooth sensitivity, pain, or special requests..."
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none transition"></textarea>
                    </div>

                    <button type="submit" id="submitBtn" disabled
                            class="w-full bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-500 hover:to-cyan-500 text-white font-bold py-3.5 px-6 rounded-2xl shadow-lg shadow-teal-600/20 disabled:opacity-40 disabled:shadow-none disabled:cursor-not-allowed transition">
                        Confirm Appointment Request →
                    </button>
                </form>
            </section>

            <!-- Right Column: My Appointments History -->
            <section id="my-appointments" class="xl:col-span-5 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 h-fit">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">My Appointments</h2>
                        <p class="text-xs text-slate-500">Live status of your scheduled visits</p>
                    </div>
                    <span class="text-xs font-bold bg-slate-100 text-slate-700 px-3 py-1 rounded-full">
                        <?= count($myAppointments) ?> Total
                    </span>
                </div>

                <?php if (empty($myAppointments)): ?>
                    <div class="text-center py-12 px-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200">
                        <div class="text-2xl mb-2">🗓</div>
                        <p class="text-sm font-semibold text-slate-700">No appointments booked yet</p>
                        <p class="text-xs text-slate-500 mt-1">Use the form on the left to reserve your first visit.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3.5 max-h-[540px] overflow-y-auto pr-1">
                        <?php foreach ($myAppointments as $apt): ?>
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
                            <div class="p-4 rounded-2xl border border-slate-200/90 hover:border-teal-300 bg-white shadow-sm transition space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-[11px] font-mono font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-lg">
                                        <?= Security::escape($apt['reference_code']) ?>
                                    </span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold border <?= $badgeClass ?>">
                                        <?= Security::escape($apt['status']) ?>
                                    </span>
                                </div>
                                <div class="font-bold text-slate-900 text-sm"><?= Security::escape($apt['service_name']) ?></div>
                                <div class="flex justify-between items-center text-xs text-slate-600">
                                    <span class="font-semibold text-teal-700"><?= Security::escape($apt['dentist_name']) ?></span>
                                    <span class="font-bold text-slate-800">₱<?= number_format((float) $apt['price'], 2) ?></span>
                                </div>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                    <span class="font-medium text-slate-700">📅 <?= Security::escape(date('M j, Y', strtotime($apt['appointment_date']))) ?></span>
                                    <span>⏰ <?= Security::escape(date('g:i A', strtotime($apt['start_time']))) ?> – <?= Security::escape(date('g:i A', strtotime($apt['end_time']))) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="<?= Security::escape(Security::url('/assets/js/booking.js')) ?>"></script>
</body>
</html>
