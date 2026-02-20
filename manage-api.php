<?php 
include 'config.php';
checkLogin();

// Hanya Superadmin/Admin yang boleh kelola API
if (!in_array($_SESSION['role'], ['superadmin', 'admin'])) {
    header("Location: dashboard.php"); exit();
}

$msg = ''; $msg_type = '';

// Generator Random Key
function generateRandomString($length) {
    return bin2hex(random_bytes($length / 2));
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CREATE API KEY UNTUK USER TERTENTU
    if (isset($_POST['create_api'])) {
        $target_user_id = intval($_POST['target_user_id']);
        
        // Cek apakah user ini sudah punya API Key aktif (Opsional: batasi 1 key per user)
        $check = $conn->query("SELECT id FROM api_keys WHERE user_id = $target_user_id AND status = 1");
        if ($check->num_rows > 0) {
            $msg = "This user already has an active API Key. Revoke it first to generate a new one."; 
            $msg_type = "error";
        } else {
            // Ambil username untuk app_name (hanya sebagai identifier)
            $uQ = $conn->query("SELECT username FROM users WHERE id = $target_user_id");
            $uData = $uQ->fetch_assoc();
            $app_name = "API Key - " . ($uData['username'] ?? "User $target_user_id");

            $access_key = generateRandomString(32); // 32 chars
            $secret_key = generateRandomString(64); // 64 chars
            
            $stmt = $conn->prepare("INSERT INTO api_keys (user_id, app_name, access_key, secret_key) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $target_user_id, $app_name, $access_key, $secret_key);
            
            if ($stmt->execute()) {
                $msg = "API Key Generated Successfully for " . htmlspecialchars($uData['username'] ?? 'Unknown User') . "!"; 
                $msg_type = "success";
            } else {
                $msg = "Error generating key: " . $conn->error; 
                $msg_type = "error";
            }
        }
    }
    
    // REVOKE API KEY
    if (isset($_POST['revoke_api'])) {
        $id = intval($_POST['api_id']);
        $conn->query("UPDATE api_keys SET status = 0 WHERE id = $id");
        $msg = "API Key Revoked."; $msg_type = "success";
    }
}

// --- DATA FETCHING ---
// Ambil daftar API Keys beserta nama usernya
$sql_keys = "SELECT a.*, u.username, u.email 
             FROM api_keys a 
             LEFT JOIN users u ON a.user_id = u.id 
             ORDER BY a.id DESC";
$keys = $conn->query($sql_keys);

// Ambil daftar Users untuk Dropdown (Hanya user yang belum punya key aktif)
// Agar rapi, kita filter di query SQL
$sql_users = "SELECT id, user_code, username, email, role 
              FROM users 
              WHERE id NOT IN (SELECT user_id FROM api_keys WHERE status = 1)
              ORDER BY username ASC";
$available_users = $conn->query($sql_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }, colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A' }, animation: { 'fade-in-up': 'fadeInUp 0.3s ease-out forwards' }, keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } } } } }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans">
    
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 md:p-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 animate-fade-in-up">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-primary">
                            <i class="ph ph-key text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">API Credentials</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Manage Access & Secret Keys tied to user accounts.</p>
                        </div>
                    </div>
                    <button onclick="document.getElementById('apiModal').classList.remove('hidden')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-indigo-500/20 flex items-center gap-2 active:scale-95 transition-all">
                        <i class="ph ph-plus-circle text-lg"></i> Generate User Key
                    </button>
                </div>

                <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 <?= $msg_type=='success'?'bg-emerald-50 text-emerald-700 border-emerald-200':'bg-red-50 text-red-700 border-red-200' ?> animate-fade-in-up">
                    <i class="ph <?= $msg_type=='success'?'ph-check-circle':'ph-warning-circle' ?> text-xl"></i>
                    <span class="text-sm font-medium"><?= $msg ?></span>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-bold uppercase text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-6 py-4">Account Owner</th>
                                    <th class="px-6 py-4">Access Key</th>
                                    <th class="px-6 py-4">Secret Key</th>
                                    <th class="px-6 py-4">Generated On</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php if($keys->num_rows > 0): while($k = $keys->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center font-bold text-xs uppercase">
                                                <?= substr($k['username'] ?? '?', 0, 2) ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($k['username'] ?? 'Unknown User') ?></p>
                                                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($k['email'] ?? '') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-primary dark:text-indigo-400"><?= $k['access_key'] ?></td>
                                    <td class="px-6 py-4 font-mono text-xs">
                                        <div class="flex items-center gap-2 group">
                                            <span class="blur-sm group-hover:blur-none transition-all select-all"><?= $k['secret_key'] ?></span>
                                            <button onclick="copyToClipboard('<?= $k['secret_key'] ?>')" class="text-slate-400 hover:text-primary transition-colors" title="Copy Secret Key"><i class="ph ph-copy text-lg"></i></button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-slate-500">
                                        <?= date('M d, Y H:i', strtotime($k['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($k['status'] == 1): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-800"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <?php if($k['status'] == 1): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to revoke API access for this user?');">
                                            <input type="hidden" name="api_id" value="<?= $k['id'] ?>">
                                            <button type="submit" name="revoke_api" class="text-red-500 hover:text-red-700 font-bold text-xs bg-red-50 dark:bg-red-900/20 px-3 py-1.5 rounded-lg border border-red-100 dark:border-red-800 transition-colors">Revoke</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500">No API Keys have been generated yet.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <div id="apiModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="document.getElementById('apiModal').classList.add('hidden')"></div>
        <div class="fixed inset-0 z-10 w-screen flex items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-darkcard rounded-2xl shadow-2xl p-6 scale-95 transition-all" id="apiPanel">
                <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-primary mb-4">
                    <i class="ph ph-key text-2xl"></i>
                </div>
                <h3 class="font-bold text-lg mb-1 dark:text-white">Generate User API Key</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Select a user. The generated key will inherit their data access permissions.</p>
                
                <form method="POST">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Select User Account</label>
                    <select name="target_user_id" required class="w-full border border-slate-200 dark:border-slate-700 rounded-xl p-3 mb-8 outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:bg-slate-800 dark:text-white transition-all appearance-none cursor-pointer">
                        <option value="" disabled selected>-- Choose a User --</option>
                        <?php if($available_users->num_rows > 0): while($u = $available_users->fetch_assoc()): ?>
                            <option value="<?= $u['id'] ?>">
                              [<?= htmlspecialchars($u['user_code'] ?? 'NO-CODE') ?>] <?= htmlspecialchars($u['username'] ?? 'Unknown') ?> - <?= htmlspecialchars($u['email'] ?? '') ?>
                            </option>
                        <?php endwhile; else: ?>
                            <option value="" disabled>All users already have active keys.</option>
                        <?php endif; ?>
                    </select>

                    <div class="flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700 pt-4">
                        <button type="button" onclick="document.getElementById('apiModal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit" name="create_api" class="px-6 py-2.5 bg-primary hover:bg-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-500/20 active:scale-95 transition-all">Generate Keys</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="copyToast" class="fixed bottom-5 right-5 z-[70] transform transition-all duration-300 translate-y-full opacity-0">
        <div class="flex items-center gap-3 bg-slate-900 text-white shadow-xl rounded-xl px-4 py-3">
            <i class="ph ph-check-circle text-emerald-400 text-xl"></i>
            <span class="text-sm font-medium">Secret Key copied to clipboard!</span>
        </div>
    </div>

    <script>
        // Modal Animation trigger on show
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if(mutation.target.classList.contains('hidden') === false) {
                    setTimeout(() => {
                        document.getElementById('apiPanel').classList.remove('scale-95');
                        document.getElementById('apiPanel').classList.add('scale-100');
                    }, 10);
                } else {
                    document.getElementById('apiPanel').classList.add('scale-95');
                    document.getElementById('apiPanel').classList.remove('scale-100');
                }
            });
        });
        observer.observe(document.getElementById('apiModal'), { attributes: true, attributeFilter: ['class'] });

        // Copy to clipboard with Toast
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => { 
                const toast = document.getElementById('copyToast');
                toast.classList.remove('translate-y-full', 'opacity-0');
                setTimeout(() => { 
                    toast.classList.add('translate-y-full', 'opacity-0'); 
                }, 3000);
            });
        }
    </script>
</body>
</html>