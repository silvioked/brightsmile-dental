<?php
declare(strict_types=1);

use App\Core\Security;

/**
 * Shared Non-Scrolling Fixed Left Sidebar Navigation for all Authenticated Portals.
 * Expects $currentUser = $_SESSION['user'] and optional $activeNav (string).
 */
$role = $currentUser['role'] ?? 'client';
$activeNav = $activeNav ?? 'dashboard';

$roleLabels = [
    'admin'        => ['label' => 'Administrator', 'badge' => 'bg-teal-400/20 text-teal-300 border-teal-400/30'],
    'dentist'      => ['label' => 'Dentist Portal', 'badge' => 'bg-emerald-400/20 text-emerald-300 border-emerald-400/30'],
    'receptionist' => ['label' => 'Front-Desk Staff', 'badge' => 'bg-indigo-400/20 text-indigo-300 border-indigo-400/30'],
    'client'       => ['label' => 'Patient Portal', 'badge' => 'bg-cyan-400/20 text-cyan-300 border-cyan-400/30'],
];
$roleMeta = $roleLabels[$role] ?? $roleLabels['client'];

$navItems = [];
if ($role === 'admin') {
    $navItems = [
        ['id' => 'overview',     'label' => 'Analytics Overview',   'icon' => '📊', 'href' => Security::url('/admin/dashboard.php#overview')],
        ['id' => 'services',     'label' => 'Dental Services',      'icon' => '🦷', 'href' => Security::url('/admin/dashboard.php#services')],
        ['id' => 'staff',        'label' => 'Staff & Dentists',     'icon' => '👥', 'href' => Security::url('/admin/dashboard.php#staff')],
        ['id' => 'queue',        'label' => 'Appointment Queue',    'icon' => '🗂', 'href' => Security::url('/receptionist/appointments.php')],
    ];
} elseif ($role === 'receptionist') {
    $navItems = [
        ['id' => 'queue',        'label' => 'Appointment Queue',    'icon' => '🗓', 'href' => Security::url('/receptionist/appointments.php#queue')],
        ['id' => 'pending',      'label' => 'Pending Approvals',    'icon' => '⏳', 'href' => Security::url('/receptionist/appointments.php?status=Pending')],
        ['id' => 'confirmed',    'label' => 'Confirmed Schedule',   'icon' => '✓',  'href' => Security::url('/receptionist/appointments.php?status=Confirmed')],
        ['id' => 'walkin',       'label' => 'Walk-In Registration', 'icon' => '➕', 'href' => Security::url('/receptionist/appointments.php#walkin')],
    ];
} elseif ($role === 'dentist') {
    $navItems = [
        ['id' => 'schedule',     'label' => 'My Daily Schedule',    'icon' => '📅', 'href' => Security::url('/dentist/schedule.php')],
        ['id' => 'today',        'label' => 'Today\'s Patients',    'icon' => '🦷', 'href' => Security::url('/dentist/schedule.php?date=' . date('Y-m-d'))],
        ['id' => 'history',      'label' => 'Clinical Records',     'icon' => '📋', 'href' => Security::url('/dentist/schedule.php#clinical-history')],
    ];
} else {
    $navItems = [
        ['id' => 'book',         'label' => 'Book Appointment',     'icon' => '➕', 'href' => Security::url('/client/book.php#book-section')],
        ['id' => 'appointments', 'label' => 'My Appointments',      'icon' => '🗓', 'href' => Security::url('/client/book.php#my-appointments')],
    ];
}
?>
<!-- Locked Non-Scrolling Left Sidebar -->
<aside class="w-72 h-screen overflow-hidden bg-slate-950 text-slate-200 flex-shrink-0 hidden lg:flex flex-col justify-between border-r border-slate-800/80 select-none">
    <!-- Top Brand & Navigation -->
    <div class="p-6 space-y-6">
        <!-- Clinic Brand -->
        <div class="flex items-center gap-3 pb-5 border-b border-slate-800/80">
            <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-teal-400 to-cyan-500 text-slate-950 flex items-center justify-center font-extrabold text-lg shadow-lg shadow-teal-500/20">
                ✦
            </span>
            <div>
                <span class="font-extrabold text-white text-lg tracking-tight block leading-none">BrightSmile</span>
                <span class="inline-block mt-1.5 text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider border <?= $roleMeta['badge'] ?>">
                    <?= Security::escape($roleMeta['label']) ?>
                </span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="space-y-1.5">
            <div class="px-3 text-[11px] font-extrabold uppercase tracking-widest text-slate-500 mb-2">
                Main Navigation
            </div>
            <?php foreach ($navItems as $item): ?>
                <?php $isActive = ($activeNav === $item['id']); ?>
                <a href="<?= Security::escape($item['href']) ?>"
                   class="flex items-center gap-3.5 px-4 py-2.5 rounded-2xl text-sm font-semibold transition <?= $isActive ? 'bg-gradient-to-r from-teal-600 to-cyan-600 text-white shadow-lg shadow-teal-600/25' : 'text-slate-400 hover:text-white hover:bg-white/5' ?>">
                    <span class="text-base leading-none"><?= $item['icon'] ?></span>
                    <span><?= Security::escape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Security & Conflict-Engine Status Card -->
        <div class="p-3.5 rounded-2xl bg-slate-900/90 border border-slate-800/90 space-y-1.5">
            <div class="flex items-center gap-2 text-xs font-bold text-teal-400">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Conflict Guard Active</span>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed">
                Real-time slot locking & RBAC session protection enabled.
            </p>
        </div>
    </div>

    <!-- Bottom User Profile & Sign Out -->
    <div class="p-5 border-t border-slate-800/80 bg-slate-900/50 space-y-3">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-teal-500/20 border border-teal-400/30 text-teal-300 font-extrabold text-sm flex items-center justify-center">
                <?= Security::escape(strtoupper(substr($currentUser['name'] ?? 'U', 0, 1))) ?>
            </span>
            <div class="overflow-hidden">
                <div class="text-sm font-bold text-white truncate"><?= Security::escape($currentUser['name'] ?? '') ?></div>
                <div class="text-xs text-slate-400 truncate"><?= Security::escape($currentUser['email'] ?? '') ?></div>
            </div>
        </div>
        <a href="<?= Security::escape(Security::url('/logout.php')) ?>"
           class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/25 text-xs font-bold transition">
            <span>Sign Out</span>
            <span>→</span>
        </a>
    </div>
</aside>
