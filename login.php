<?php
include 'config.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    // Cek apakah ini sesi setup account yang belum selesai
    if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1) {
        header("Location: setup-account.php");
        exit();
    }
    header("Location: dashboard");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = $_POST['username_email'];
    $pass  = $_POST['password'];

    // Update query untuk mengambil kolom must_change_password
    $stmt = $conn->prepare("SELECT id, username, password, role, must_change_password FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $input, $input);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            // Set Session Standard
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            
            // LOGIC BARU: Cek First Time Login
            if ($row['must_change_password'] == 1) {
                $_SESSION['must_change_password'] = 1; // Flag session
                header("Location: setup-account.php");
            } else {
                header("Location: dashboard");
            }
            exit();
        } else {
            $error = "Password salah.";
        }
    } else {
        $error = "Akun tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - IoT Platform</title>
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
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'slide-in': 'slideIn 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        slideIn: {
                            '0%': { opacity: '0', transform: 'translateX(-20px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' },
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-white text-slate-800 font-sans antialiased overflow-hidden">

    <div class="flex min-h-screen w-full">
        
        <div class="hidden lg:flex w-1/2 relative bg-slate-900 overflow-hidden">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center opacity-40 hover:scale-105 transition-transform duration-[20s] ease-linear"></div>
            
            <div class="relative z-10 flex flex-col justify-center px-16 text-white h-full w-full bg-gradient-to-r from-slate-900/90 to-slate-900/40">
                <div class="animate-slide-in" style="animation-delay: 0.1s;">
                    <div class="h-12 w-12 bg-indigo-500 rounded-xl flex items-center justify-center mb-6 shadow-lg shadow-indigo-500/30">
                        <i class="ph ph-lightning text-2xl text-white"></i>
                    </div>
                    <h1 class="text-4xl font-bold mb-4 leading-tight">Manage Your IoT Connectivity <br> <span class="text-indigo-400">Smartly & Efficiently.</span></h1>
                    <p class="text-slate-300 text-lg max-w-md leading-relaxed">Real-time monitoring for Telkomsel SIM cards, data usage analytics, and multi-company management in one platform.</p>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white relative">
            <div class="w-full max-w-[420px] animate-fade-in-up">
                
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">Welcome Back</h2>
                    <p class="text-slate-500">Please enter your details to sign in.</p>
                </div>

                <?php if($error): ?>
                    <div class="mb-6 flex items-center gap-3 p-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl animate-pulse">
                        <i class="ph ph-warning-circle text-xl"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    
                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-slate-700">Username or Email</label>
                        <div class="relative group">
                            <i class="ph ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-600 transition-colors"></i>
                            <input type="text" name="username_email" placeholder="Enter your username" required 
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-slate-700">Password</label>
                        <div class="relative group">
                            <i class="ph ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-indigo-600 transition-colors"></i>
                            <input type="password" name="password" placeholder="••••••••" required 
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
                            <span class="text-slate-600">Remember me</span>
                        </label>
                        <a href="#" class="text-indigo-600 font-semibold hover:underline">Forgot password?</a>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-indigo-500/30 active:scale-95 flex items-center justify-center gap-2">
                        Sign In <i class="ph ph-arrow-right"></i>
                    </button>

                </form>

                <p class="mt-8 text-center text-sm text-slate-400">
                    &copy; <?= date('Y') ?> IoT Platform. All rights reserved.
                </p>
            </div>
        </div>

    </div>
</body>
</html>