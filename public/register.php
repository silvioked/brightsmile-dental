<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../src/Core/Security.php';
require_once __DIR__ . '/../src/Core/AuthMiddleware.php';

use App\Config\Database;
use App\Core\AuthMiddleware;
use App\Core\Security;

AuthMiddleware::redirectIfAuthenticated();
AuthMiddleware::requireCsrfOnPost();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName  = trim((string) ($_POST['last_name'] ?? ''));
    $email     = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone     = trim((string) ($_POST['phone'] ?? ''));
    $dob       = trim((string) ($_POST['date_of_birth'] ?? ''));
    $password  = (string) ($_POST['password'] ?? '');

    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || strlen($password) < 8) {
        $error = 'Please fill out all required fields and use a password of at least 8 characters.';
    } else {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                INSERT INTO users (role_id, first_name, last_name, email, phone, date_of_birth, password_hash)
                VALUES (4, :first_name, :last_name, :email, :phone, :dob, :password_hash)
            ');
            $stmt->execute([
                ':first_name'    => $firstName,
                ':last_name'     => $lastName,
                ':email'         => $email,
                ':phone'         => $phone,
                ':dob'           => $dob ?: null,
                ':password_hash' => Security::hashPassword($password),
            ]);
            $success = 'Registration successful! Your patient account is ready.';
        } catch (Throwable $e) {
            $error = 'Unable to register account. That email address may already be registered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration — BrightSmile Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-800 flex items-center justify-center p-4 sm:p-6 relative overflow-x-hidden">
    <div class="fixed -top-32 -right-32 w-96 h-96 rounded-full bg-teal-500/20 blur-3xl pointer-events-none"></div>
    <div class="fixed -bottom-32 -left-32 w-96 h-96 rounded-full bg-cyan-500/20 blur-3xl pointer-events-none"></div>

    <div class="max-w-xl w-full bg-white rounded-3xl shadow-2xl border border-slate-100 p-8 sm:p-10 relative z-10">
        <div class="flex items-center justify-between mb-6">
            <div class="inline-flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-teal-500 to-cyan-600 text-white flex items-center justify-center font-extrabold shadow">✦</span>
                <div>
                    <span class="font-extrabold text-slate-900 block leading-none">BrightSmile</span>
                    <span class="text-[11px] text-teal-600 font-semibold uppercase tracking-widest">Patient Onboarding</span>
                </div>
            </div>
            <a href="<?= Security::escape(Security::url('/login.php')) ?>" class="text-xs font-bold text-slate-500 hover:text-teal-600 transition">
                ← Back to Sign In
            </a>
        </div>

        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Create Patient Account</h1>
        <p class="text-sm text-slate-500 mt-1 mb-6">Book dental appointments online and track your visit history.</p>

        <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center gap-2.5">
                <span class="font-bold">⚠</span>
                <span><?= Security::escape($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-5 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
                <span>✓ <?= Security::escape($success) ?></span>
                <a href="<?= Security::escape(Security::url('/login.php')) ?>" class="bg-emerald-600 text-white px-3.5 py-1.5 rounded-xl font-bold text-xs hover:bg-emerald-700">
                    Sign In Now →
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4 text-sm">
            <?= Security::csrfField() ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">First Name *</label>
                    <input type="text" name="first_name" required placeholder="Juan"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Last Name *</label>
                    <input type="text" name="last_name" required placeholder="Dela Cruz"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email Address *</label>
                <input type="email" name="email" required placeholder="patient@example.com"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Mobile Phone *</label>
                    <input type="text" name="phone" required placeholder="+63 917 000 0000"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Date of Birth</label>
                    <input type="date" name="date_of_birth"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Password (Min. 8 Characters) *</label>
                <input type="password" name="password" minlength="8" required placeholder="••••••••"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:outline-none">
            </div>
            <button type="submit"
                    class="w-full bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-500 hover:to-cyan-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-teal-600/25 transition mt-2">
                Complete Registration →
            </button>
        </form>
    </div>
</body>
</html>
