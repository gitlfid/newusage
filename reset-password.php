<?php
include 'config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$showForm = true;

if (empty($token)) {
    $error = "Invalid token.";
    $showForm = false;
} else {
    // Validasi Token & Expiry
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        $error = "Token is invalid or has expired.";
        $showForm = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $showForm) {
    $pass = $_POST['password'];
    $conf = $_POST['confirm_password'];

    if (strlen($pass) < 8) {
        $error = "Password minimum 8 characters.";
    } elseif ($pass !== $conf) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        // Update Password & Clear Token
        $upd = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
        $upd->bind_param("ss", $hashed, $token);
        
        if ($upd->execute()) {
            $success = "Password successfully reset! Redirecting...";
            $showForm = false;
            echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 3000);</script>";
        } else {
            $error = "System error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { primary: '#4F46E5' } } } }</script>
</head>
<body class="bg-slate-50 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-xl border border-slate-100">
        
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4 text-primary"><i class="ph ph-lock-key-open text-2xl"></i></div>
            <h2 class="text-2xl font-bold text-slate-800">New Password</h2>
            <?php if($showForm): ?>
            <p class="text-slate-500 text-sm mt-1">Create a new, strong password.</p>
            <?php endif; ?>
        </div>

        <?php if($error): ?>
            <div class="mb-6 p-4 text-sm text-red-600 bg-red-50 rounded-xl border border-red-100 flex items-center gap-2">
                <i class="ph ph-warning-circle text-lg"></i> <?= $error ?>
            </div>
            <?php if(!$showForm): ?>
                <a href="login.php" class="block w-full text-center bg-slate-100 text-slate-600 font-bold py-3 rounded-xl hover:bg-slate-200 transition-all">Back to Login</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="mb-6 p-4 text-sm text-emerald-700 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center gap-2">
                <i class="ph ph-check-circle text-lg"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if($showForm): ?>
        <form method="POST" class="space-y-5">
            <div>
                <label class="text-sm font-bold text-slate-600 uppercase text-xs">New Password</label>
                <input type="password" name="password" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            </div>
            <div>
                <label class="text-sm font-bold text-slate-600 uppercase text-xs">Confirm Password</label>
                <input type="password" name="confirm_password" required class="w-full mt-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-indigo-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-indigo-500/20">Change Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>