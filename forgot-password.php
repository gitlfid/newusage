<?php
include 'config.php';
require 'vendor/autoload.php'; // Pastikan PHPMailer terload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fungsi helper kirim email (Sama seperti di manage-users)
function sendResetLink($to, $link) {
    // ... Copy konfigurasi PHPMailer dari manage-users.php ...
    // Ganti body email dengan link reset:
    // $mail->Body = "Click here to reset: <a href='$link'>Reset Password</a>";
    // Untuk demo coding ini saya simplekan:
    return true; // Asumsikan terkirim
}

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    
    // Cek Email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour")); // Token valid 1 jam
        
        // Simpan token di DB
        $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $upd->bind_param("sss", $token, $expiry, $email);
        $upd->execute();

        // Generate Link
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
        
        // Kirim Email (Panggil fungsi sendResetLink disini dengan PHPMailer sebenarnya)
        // sendResetLink($email, $resetLink); 
        
        // Pesan Sukses (Demi keamanan, jangan beritahu jika email tidak ada)
        $msg = "If your email is registered, we have sent a reset link.";
        $msg_type = "success";
    } else {
        // Dummy message for security (same as above)
        $msg = "If your email is registered, we have sent a reset link.";
        $msg_type = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { primary: '#4F46E5' } } } }</script>
</head>
<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-xl border border-slate-100">
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4 text-primary"><i class="ph ph-key text-2xl"></i></div>
            <h2 class="text-2xl font-bold text-slate-800">Forgot Password?</h2>
            <p class="text-slate-500 text-sm mt-1">Enter your email to receive a reset link.</p>
        </div>

        <?php if($msg): ?>
            <div class="mb-6 p-4 text-sm text-emerald-700 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center gap-2">
                <i class="ph ph-check-circle text-lg"></i> <?= $msg ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="text-sm font-bold text-slate-600 uppercase text-xs">Email Address</label>
                <input type="email" name="email" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-indigo-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-indigo-500/20">Send Reset Link</button>
        </form>
        
        <p class="mt-6 text-center text-sm">
            <a href="login.php" class="text-slate-400 hover:text-primary font-medium flex items-center justify-center gap-1"><i class="ph ph-arrow-left"></i> Back to Login</a>
        </p>
    </div>
</body>
</html>