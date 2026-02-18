<?php
include 'config.php';

// Security Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['must_change_password'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($new_pass !== $conf_pass) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        // Update Password & Matikan Flag must_change_password
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $uid = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashed, $uid);
        
        if ($stmt->execute()) {
            unset($_SESSION['must_change_password']); // Hapus flag session
            echo "<script>alert('Account setup complete! Redirecting to dashboard...'); window.location='dashboard';</script>";
            exit();
        } else {
            $error = "Terjadi kesalahan sistem.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Account - IoT Platform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#4F46E5', dark: '#0F172A' },
                    animation: { 'fade-in-up': 'fadeInUp 0.5s ease-out forwards' },
                    keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(20px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans antialiased overflow-hidden">

    <div class="flex min-h-screen w-full">
        <div class="hidden lg:flex w-1/2 relative bg-slate-900 overflow-hidden">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1563986768609-322da13575f3?q=80&w=1470&auto=format&fit=crop')] bg-cover bg-center opacity-40"></div>
            <div class="relative z-10 flex flex-col justify-center px-16 text-white h-full bg-gradient-to-r from-slate-900/90 to-slate-900/20">
                <div class="animate-fade-in-up">
                    <div class="h-12 w-12 bg-emerald-500 rounded-xl flex items-center justify-center mb-6 shadow-lg shadow-emerald-500/30">
                        <i class="ph ph-shield-check text-2xl text-white"></i>
                    </div>
                    <h1 class="text-4xl font-bold mb-4 leading-tight">Secure Your Account</h1>
                    <p class="text-slate-300 text-lg max-w-md leading-relaxed">Please set up a new password to activate your account and access the dashboard.</p>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
            <div class="w-full max-w-[420px] animate-fade-in-up" style="animation-delay: 0.1s;">
                
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">Setup Password</h2>
                    <p class="text-slate-500">Create a strong password for your first login.</p>
                </div>

                <?php if($error): ?>
                    <div class="mb-6 flex items-center gap-3 p-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl">
                        <i class="ph ph-warning-circle text-xl"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    
                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-slate-700">New Password</label>
                        <div class="relative group">
                            <i class="ph ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-600 transition-colors"></i>
                            <input type="password" name="new_password" placeholder="Min. 8 characters" required minlength="8"
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-slate-700">Confirm Password</label>
                        <div class="relative group">
                            <i class="ph ph-check-circle absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-600 transition-colors"></i>
                            <input type="password" name="confirm_password" placeholder="Re-enter password" required 
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/30 active:scale-95 flex items-center justify-center gap-2">
                        Activate Account <i class="ph ph-arrow-right"></i>
                    </button>

                </form>
            </div>
        </div>
    </div>
</body>
</html>