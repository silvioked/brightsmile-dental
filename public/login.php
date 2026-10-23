<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../src/Core/Security.php';
require_once __DIR__ . '/../src/Core/AuthMiddleware.php';

use App\Core\AuthMiddleware;
use App\Core\Security;

AuthMiddleware::redirectIfAuthenticated();
AuthMiddleware::requireCsrfOnPost();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $result = AuthMiddleware::attemptLogin($email, $password);
    if ($result['success']) {
        header('Location: ' . $result['redirect']);
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — BrightSmile Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-800 flex items-center justify-center p-4 sm:p-6 relative overflow-x-hidden">
    <!-- Ambient Decorative Background Glows -->
    <div class="fixed -top-32 -left-32 w-96 h-96 rounded-full bg-teal-500/20 blur-3xl pointer-events-none"></div>
    <div class="fixed -bottom-32 -right-32 w-96 h-96 rounded-full bg-cyan-500/20 blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden grid grid-cols-1 lg:grid-cols-12 border border-white/10 relative z-10">
        <!-- Left Hero Panel -->
        <div class="lg:col-span-6 bg-gradient-to-br from-teal-900 via-teal-800 to-slate-900 text-white p-8 sm:p-12 flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -right-16 -bottom-16 w-64 h-64 rounded-full bg-teal-400/10 blur-2xl pointer-events-none"></div>

            <div>
                <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/15 mb-8">
                    <span class="w-8 h-8 rounded-xl bg-teal-400 text-slate-950 flex items-center justify-center font-extrabold text-lg shadow">
                        ✦
                    </span>
                    <div>
                        <span class="font-bold tracking-tight text-white block leading-none">BrightSmile</span>
                        <span class="text-[11px] text-teal-200 uppercase tracking-widest">Dental Care Studio</span>
                    </div>
                </div>

                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight leading-tight mb-4">
                    Modern Dental Care, <span class="text-teal-300">Effortless</span> Scheduling.
                </h1>
                <p class="text-teal-100/80 text-sm leading-relaxed mb-8">
                    Experience conflict-free online appointment booking, real-time dentist availability, and digital clinical treatment records in one unified portal.
                </p>

                <div class="space-y-3.5 text-sm">
                    <div class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl p-3">
                        <span class="w-8 h-8 rounded-lg bg-teal-500/20 text-teal-300 flex items-center justify-center font-bold">✓</span>
                        <div>
                            <div class="font-semibold text-white">Real-Time Conflict Prevention</div>
                            <div class="text-xs text-teal-200/70">Instant slot locking with zero double bookings</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl p-3">
                        <span class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-300 flex items-center justify-center font-bold">🛡</span>
                        <div>
                            <div class="font-semibold text-white">Role-Based Clinical Security</div>
                            <div class="text-xs text-teal-200/70">Dedicated portals for Patients, Dentists, Staff & Admin</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick-Fill Demo Switcher -->
            <div class="mt-10 pt-6 border-t border-white/10">
                <div class="text-xs font-semibold uppercase tracking-wider text-teal-300 mb-2.5">
                    One-Click Demo Login (Password: <code class="bg-black/30 px-1.5 py-0.5 rounded">password</code>)
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <button type="button" onclick="fillDemo('patient@dentalclinic.local')"
                            class="text-xs bg-white/10 hover:bg-white/20 border border-white/15 rounded-xl py-2 px-2.5 font-semibold transition text-center">
                        👤 Patient
                    </button>
                    <button type="button" onclick="fillDemo('frontdesk@dentalclinic.local')"
                            class="text-xs bg-white/10 hover:bg-white/20 border border-white/15 rounded-xl py-2 px-2.5 font-semibold transition text-center">
                        🗂 Staff
                    </button>
                    <button type="button" onclick="fillDemo('dr.reyes@dentalclinic.local')"
                            class="text-xs bg-white/10 hover:bg-white/20 border border-white/15 rounded-xl py-2 px-2.5 font-semibold transition text-center">
                        🦷 Dentist
                    </button>
                    <button type="button" onclick="fillDemo('admin@dentalclinic.local')"
                            class="text-xs bg-white/10 hover:bg-white/20 border border-white/15 rounded-xl py-2 px-2.5 font-semibold transition text-center">
                        ⚡ Admin
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Login Form Panel -->
        <div class="lg:col-span-6 p-8 sm:p-12 flex flex-col justify-center bg-white">
            <div class="max-w-sm mx-auto w-full">
                <div class="mb-8">
                    <span class="text-xs font-bold uppercase tracking-widest text-teal-600 bg-teal-50 px-3 py-1 rounded-full">Welcome Back</span>
                    <h2 class="text-2xl font-extrabold text-slate-900 mt-3">Sign in to your account</h2>
                    <p class="text-sm text-slate-500 mt-1">Enter your credentials or select a demo role on the left.</p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-start gap-3">
                        <span class="font-bold text-rose-500">⚠</span>
                        <span><?= Security::escape($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    <?= Security::csrfField() ?>
                    <div>
                        <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Email Address</label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="you@example.com"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 text-sm text-slate-900 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 focus:outline-none transition">
                    </div>

                    <button type="submit"
                            class="w-full bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-500 hover:to-cyan-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-teal-600/25 transition transform active:scale-[0.99]">
                        Sign In to Portal →
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                    <p class="text-sm text-slate-500">
                        New patient at BrightSmile?
                        <a href="<?= Security::escape(Security::url('/register.php')) ?>" class="text-teal-600 font-bold hover:text-teal-700 hover:underline ml-1">
                            Create Patient Account
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillDemo(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password';
        }
    </script>
</body>
</html>
