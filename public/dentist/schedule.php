<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Core/AuthMiddleware.php';
require_once __DIR__ . '/../../src/Models/Appointment.php';
require_once __DIR__ . '/../../src/Models/TreatmentRecord.php';

use App\Core\AuthMiddleware;
use App\Core\Security;
use App\Models\Appointment;
use App\Models\TreatmentRecord;

AuthMiddleware::requireRole([AuthMiddleware::ROLE_DENTIST]);
AuthMiddleware::requireCsrfOnPost();

$appointmentModel = new Appointment();
$treatmentModel   = new TreatmentRecord();
$currentUser      = $_SESSION['user'];
$dentistId        = (int) $currentUser['id'];
$feedback         = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $teeth         = trim((string) ($_POST['tooth_numbers'] ?? ''));
    $diagnosis     = trim((string) ($_POST['diagnosis'] ?? ''));
    $procedure     = trim((string) ($_POST['procedure_performed'] ?? ''));
    $prescription  = trim((string) ($_POST['prescription'] ?? ''));
    $notes         = trim((string) ($_POST['dentist_notes'] ?? ''));
    $followUp      = trim((string) ($_POST['follow_up_date'] ?? ''));

    if ($appointmentId && $diagnosis !== '' && $procedure !== '') {
        $feedback = $treatmentModel->saveRecord(
            $appointmentId,
            $dentistId,
            $teeth ?: null,
            $diagnosis,
            $procedure,
            $prescription ?: null,
            $notes ?: null,
            $followUp ?: null
        );
    } else {
        $feedback = ['success' => false, 'message' => 'Diagnosis and Procedure Performed are required to save a treatment record.'];
    }
}

$dateFilter    = trim((string) ($_GET['date'] ?? ''));
$patientIdHist = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

$schedule       = $appointmentModel->getDentistAppointments($dentistId, $dateFilter ?: null);
$patientHistory = $patientIdHist ? $treatmentModel->getPatientHistory($patientIdHist) : [];
$activeNav      = ($dateFilter === date('Y-m-d')) ? 'today' : ($patientIdHist ? 'history' : 'schedule');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Clinical Workspace — BrightSmile Dental</title>
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
                    <h1 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight truncate">Dentist Clinical Workspace</h1>
                    <p class="text-xs text-slate-500 hidden sm:block">Dr. <?= Security::escape($currentUser['name']) ?> — Assigned schedule & patient dental records</p>
                </div>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                <span class="hidden sm:inline-flex items-center gap-2 text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 px-3.5 py-1.5 rounded-full">
                    <span>🦷</span>
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
                <!-- Left Column: Assigned Schedule & Clinical Note Entry -->
                <section class="xl:col-span-7 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80">
                    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">My Patient Schedule</h2>
                            <p class="text-xs text-slate-500">Assigned appointments and clinical treatment documentation</p>
                        </div>
                        <form method="GET" class="flex items-center gap-2 text-xs">
                            <input type="date" name="date" value="<?= Security::escape($dateFilter) ?>"
                                   class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-semibold">
                            <button type="submit" class="bg-slate-900 text-white font-bold px-4 py-2 rounded-xl hover:bg-slate-800">
                                Filter Date
                            </button>
                        </form>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($schedule)): ?>
                            <div class="text-center py-12 px-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200">
                                <p class="text-sm font-semibold text-slate-600">No appointments scheduled for the selected criteria.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($schedule as $apt): ?>
                            <div class="p-5 rounded-2xl border border-slate-200/90 hover:border-teal-300 bg-white shadow-sm transition space-y-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-[11px] font-mono font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-lg">
                                            <?= Security::escape($apt['reference_code']) ?>
                                        </span>
                                        <h3 class="font-extrabold text-base text-slate-900 mt-1.5"><?= Security::escape($apt['patient_name']) ?></h3>
                                        <p class="text-xs font-bold text-teal-600"><?= Security::escape($apt['service_name']) ?></p>
                                    </div>
                                    <div class="text-right text-xs">
                                        <div class="font-bold text-slate-800">📅 <?= Security::escape(date('M j, Y', strtotime($apt['appointment_date']))) ?></div>
                                        <div class="text-slate-500 mt-0.5">
                                            ⏰ <?= Security::escape(date('g:i A', strtotime($apt['start_time']))) ?> –
                                            <?= Security::escape(date('g:i A', strtotime($apt['end_time']))) ?>
                                        </div>
                                        <span class="inline-block mt-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">
                                            <?= Security::escape($apt['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if (!empty($apt['patient_notes'])): ?>
                                    <div class="text-xs bg-amber-50/80 border border-amber-200 text-amber-900 p-3 rounded-xl">
                                        <strong class="uppercase tracking-wider text-[10px] text-amber-700 block mb-0.5">Chief Complaint</strong>
                                        <?= Security::escape($apt['patient_notes']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="pt-3 border-t border-slate-100 flex flex-col gap-2 text-xs">
                                    <div class="flex justify-between items-center">
                                        <a href="?patient_id=<?= (int) $apt['patient_id'] ?>#clinical-history"
                                           class="font-bold text-teal-600 hover:text-teal-700 bg-teal-50 px-3 py-1.5 rounded-xl">
                                            📋 View Patient Dental Chart →
                                        </a>
                                    </div>

                                    <details class="w-full mt-1">
                                        <summary class="cursor-pointer text-indigo-600 font-bold hover:underline text-right">
                                            + Record Clinical Treatment Notes
                                        </summary>
                                        <form method="POST" class="mt-3 p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3 text-left">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="appointment_id" value="<?= (int) $apt['id'] ?>">

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Tooth Number(s)</label>
                                                    <input type="text" name="tooth_numbers" placeholder="e.g., #14, #15"
                                                           class="w-full border border-slate-200 bg-white rounded-xl px-3 py-2">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Follow-Up Date</label>
                                                    <input type="date" name="follow_up_date"
                                                           class="w-full border border-slate-200 bg-white rounded-xl px-3 py-2">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Clinical Diagnosis *</label>
                                                <textarea name="diagnosis" rows="2" required
                                                          class="w-full border border-slate-200 bg-white rounded-xl px-3 py-2"></textarea>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Procedure Performed *</label>
                                                <textarea name="procedure_performed" rows="2" required
                                                          class="w-full border border-slate-200 bg-white rounded-xl px-3 py-2"></textarea>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Prescription / Post-Op Instructions</label>
                                                <textarea name="prescription" rows="2"
                                                          class="w-full border border-slate-200 bg-white rounded-xl px-3 py-2"></textarea>
                                            </div>

                                            <button type="submit"
                                                    class="bg-emerald-600 text-white font-bold px-4 py-2.5 rounded-xl hover:bg-emerald-700 shadow-sm transition">
                                                Save Clinical Record & Complete Visit
                                            </button>
                                        </form>
                                    </details>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Right Column: Patient Dental History Chart -->
                <section id="clinical-history" class="xl:col-span-5 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 h-fit">
                    <h2 class="text-lg font-extrabold text-slate-900 mb-1">Patient Clinical History</h2>
                    <p class="text-xs text-slate-500 mb-5">Prior diagnoses, procedures, and prescriptions</p>

                    <?php if (!$patientIdHist): ?>
                        <div class="text-center py-10 px-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200">
                            <div class="text-2xl mb-2">🦷</div>
                            <p class="text-xs text-slate-500">Click <strong>"View Patient Dental Chart"</strong> on any appointment card to load their historical clinical records.</p>
                        </div>
                    <?php elseif (empty($patientHistory)): ?>
                        <div class="text-center py-10 px-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200">
                            <p class="text-xs text-slate-500">No prior treatment records found for this patient.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($patientHistory as $rec): ?>
                                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs space-y-2">
                                    <div class="flex justify-between items-center font-bold text-slate-800 border-b border-slate-200 pb-2">
                                        <span><?= Security::escape(date('M j, Y', strtotime($rec['appointment_date']))) ?> — <?= Security::escape($rec['service_name']) ?></span>
                                        <span class="text-teal-700"><?= Security::escape($rec['dentist_name']) ?></span>
                                    </div>
                                    <?php if (!empty($rec['tooth_numbers'])): ?>
                                        <div><strong class="text-slate-600">Teeth:</strong> <?= Security::escape($rec['tooth_numbers']) ?></div>
                                    <?php endif; ?>
                                    <div><strong class="text-slate-600">Diagnosis:</strong> <?= Security::escape($rec['diagnosis']) ?></div>
                                    <div><strong class="text-slate-600">Procedure:</strong> <?= Security::escape($rec['procedure_performed']) ?></div>
                                    <?php if (!empty($rec['prescription'])): ?>
                                        <div><strong class="text-slate-600">Prescription:</strong> <?= Security::escape($rec['prescription']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
