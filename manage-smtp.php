<?php 
include 'config.php';
checkLogin();

// // SECURITY: Hanya Superadmin
// if($_SESSION['role'] != 'superadmin') {
//     header("Location: dashboard"); exit();
// }

enforcePermission('settings');

$msg = '';

// Load PHPMailer
require 'vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- SAVE CONFIGURATION ---
    if(isset($_POST['save_config'])) {
        $host = $_POST['host'];
        $port = $_POST['port'];
        $user = $_POST['username'];
        $pass = $_POST['password'];
        $enc  = $_POST['encryption'];
        $from = $_POST['from_email'];
        $name = $_POST['from_name'];

        $stmt = $conn->prepare("UPDATE smtp_settings SET host=?, port=?, username=?, password=?, encryption=?, from_email=?, from_name=? WHERE id=1");
        $stmt->bind_param("sisssss", $host, $port, $user, $pass, $enc, $from, $name);
        
        if($stmt->execute()) $msg = 'success_save';
        else $msg = 'error_save';
    }

    // --- SEND TEST EMAIL ---
    // Note: Kita cek input hidden 'test_email_trigger' karena tombol mungkin disabled oleh JS
    if(isset($_POST['test_email_trigger'])) {
        $target = $_POST['test_target'];
        $cfg = getSmtpConfig();
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            
            // --- FIX SSL ERROR (Peer Certificate Mismatch) ---
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            // --------------------------------------------------

            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = $cfg['encryption'];
            $mail->Port       = $cfg['port'];

            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($target);

            $mail->isHTML(true);
            $mail->Subject = 'Test Email from IoT Platform';
            $mail->Body    = '
                <div style="font-family: Inter, Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;">
                    <h2 style="color: #4F46E5;">SMTP Configuration Works!</h2>
                    <p>This email confirms that your IoT Platform can successfully send emails via the configured SMTP server.</p>
                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                    <p style="font-size: 12px; color: #888;">Sent from IoT Connectivity Platform</p>
                </div>
            ';

            $mail->send();
            $msg = 'success_test';
        } catch (Exception $e) {
            $msg = 'error_test: ' . $mail->ErrorInfo;
        }
    }
}

$data = getSmtpConfig();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMTP Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        tailwind.config = { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] }, // Set Font Inter
                    colors: { primary: '#4F46E5', darkcard: '#24303F', darkbg: '#1A222C' },
                    animation: { 'fade-in-up': 'fadeInUp 0.5s ease-out forwards' },
                    keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } }
                } 
            } 
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 md:p-8">
                
                <div class="mb-8 animate-fade-in-up">
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-primary">
                            <i class="ph ph-envelope-simple-open text-2xl"></i>
                        </div>
                        SMTP Settings
                    </h2>
                    <p class="text-sm text-slate-500 mt-1 ml-12">Configure email server settings for notifications and alerts.</p>
                </div>

                <?php if($msg): ?>
                    <div class="mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
                        <?php if($msg == 'success_save'): ?>
                            <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-xl border border-green-200 dark:border-green-800 shadow-sm">
                                <i class="ph ph-check-circle text-xl"></i> <span class="font-semibold">Configuration saved successfully.</span>
                            </div>
                        <?php elseif($msg == 'success_test'): ?>
                            <div class="flex items-center gap-3 p-4 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 rounded-xl border border-emerald-200 dark:border-emerald-800 shadow-sm">
                                <i class="ph ph-paper-plane-tilt text-xl"></i> <span class="font-semibold">Test email sent successfully! Check your inbox.</span>
                            </div>
                        <?php elseif(strpos($msg, 'error') !== false): ?>
                            <div class="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-xl border border-red-200 dark:border-red-800 shadow-sm">
                                <i class="ph ph-warning-circle text-xl"></i> <span><?= $msg ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                    
                    <div class="xl:col-span-2 bg-white dark:bg-darkcard p-6 md:p-8 rounded-2xl shadow-lg shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white">Server Configuration</h3>
                            <span class="px-3 py-1 bg-slate-100 dark:bg-slate-700 rounded-full text-xs font-bold text-slate-500 dark:text-slate-300">PHPMailer</span>
                        </div>

                        <form method="POST" class="space-y-5">
                            <input type="hidden" name="save_config" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="md:col-span-2 space-y-1.5">
                                    <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">SMTP Host</label>
                                    <div class="relative group">
                                        <i class="ph ph-globe absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors text-lg"></i>
                                        <input type="text" name="host" value="<?= htmlspecialchars($data['host']) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all" placeholder="e.g. smtp.gmail.com" required>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">Port</label>
                                    <div class="relative group">
                                        <i class="ph ph-plugs absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors text-lg"></i>
                                        <input type="number" name="port" value="<?= htmlspecialchars($data['port']) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all" placeholder="587" required>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">Encryption Protocol</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label class="cursor-pointer relative">
                                        <input type="radio" name="encryption" value="tls" class="peer sr-only" <?= $data['encryption']=='tls'?'checked':'' ?>>
                                        <div class="p-3 text-center border border-slate-200 dark:border-slate-700 rounded-xl peer-checked:bg-primary peer-checked:text-white peer-checked:border-primary hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all">
                                            <span class="font-bold text-sm">TLS</span>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer relative">
                                        <input type="radio" name="encryption" value="ssl" class="peer sr-only" <?= $data['encryption']=='ssl'?'checked':'' ?>>
                                        <div class="p-3 text-center border border-slate-200 dark:border-slate-700 rounded-xl peer-checked:bg-primary peer-checked:text-white peer-checked:border-primary hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all">
                                            <span class="font-bold text-sm">SSL</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-700 my-2">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">SMTP Username</label>
                                    <div class="relative group">
                                        <i class="ph ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors text-lg"></i>
                                        <input type="text" name="username" value="<?= htmlspecialchars($data['username']) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all" required>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">SMTP Password</label>
                                    <div class="relative group">
                                        <i class="ph ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors text-lg"></i>
                                        <input type="password" name="password" id="smtpPass" value="<?= htmlspecialchars($data['password']) ?>" class="w-full pl-11 pr-10 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all" required>
                                        <button type="button" onclick="togglePass()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary focus:outline-none">
                                            <i class="ph ph-eye text-lg" id="eyeIcon"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">From Email</label>
                                    <div class="relative group">
                                        <i class="ph ph-at absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors text-lg"></i>
                                        <input type="email" name="from_email" value="<?= htmlspecialchars($data['from_email']) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all" required>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">Sender Name</label>
                                    <div class="relative group">
                                        <i class="ph ph-identification-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors text-lg"></i>
                                        <input type="text" name="from_name" value="<?= htmlspecialchars($data['from_name']) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all" required>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4">
                                <button type="submit" class="w-full bg-primary hover:bg-indigo-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-indigo-500/20 active:scale-95 flex items-center justify-center gap-2 group">
                                    <i class="ph ph-floppy-disk text-xl group-hover:scale-110 transition-transform"></i>
                                    Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-gradient-to-b from-white to-slate-50 dark:from-darkcard dark:to-[#1e293b] p-6 md:p-8 rounded-2xl shadow-lg shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 h-fit">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg text-emerald-600">
                                <i class="ph ph-paper-plane-tilt text-2xl"></i>
                            </div>
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white">Test Connection</h3>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 leading-relaxed">
                            Send a test email to ensure your SMTP credentials and encryption settings are working correctly before using it for notifications.
                        </p>
                        
                        <form method="POST" class="space-y-5" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML = '<i class=\'ph ph-spinner animate-spin text-xl\'></i> Sending...';">
                            <input type="hidden" name="test_email_trigger" value="1">
                            
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold uppercase text-slate-400 tracking-wider">Target Email Address</label>
                                <div class="relative group">
                                    <i class="ph ph-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors text-lg"></i>
                                    <input type="email" name="test_target" placeholder="your@email.com" class="w-full pl-11 pr-4 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:text-white transition-all" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/20 active:scale-95 flex items-center justify-center gap-2 group">
                                <span>Send Test Email</span>
                                <i class="ph ph-arrow-right font-bold group-hover:translate-x-1 transition-transform"></i>
                            </button>
                        </form>

                        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                            <h4 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-2">Common Issues</h4>
                            <ul class="text-xs text-slate-500 dark:text-slate-400 space-y-1.5 list-disc pl-4">
                                <li>Check if 2-Factor Auth requires an App Password.</li>
                                <li>Ensure Port 587 (TLS) or 465 (SSL) is open.</li>
                                <li>Verify the Host matches your certificate.</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        function togglePass() {
            const input = document.getElementById('smtpPass');
            const icon = document.getElementById('eyeIcon');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('ph-eye', 'ph-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('ph-eye-slash', 'ph-eye');
            }
        }
    </script>
</body>
</html>