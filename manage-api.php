<?php 
include 'config.php';
checkLogin();

// Hanya Superadmin/Admin yang boleh buat API
if (!in_array($_SESSION['role'], ['superadmin', 'admin'])) {
    header("Location: dashboard.php"); exit();
}

$msg = ''; $msg_type = '';

// Generator Random Key
function generateRandomString($length) {
    return bin2hex(random_bytes($length / 2));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_api'])) {
        $app_name = trim($_POST['app_name']);
        $user_id = $_SESSION['user_id']; // Terikat ke admin yang membuat
        
        $access_key = generateRandomString(32); // 32 chars
        $secret_key = generateRandomString(64); // 64 chars
        
        $stmt = $conn->prepare("INSERT INTO api_keys (user_id, app_name, access_key, secret_key) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $app_name, $access_key, $secret_key);
        if ($stmt->execute()) {
            $msg = "API Key Generated Successfully!"; $msg_type = "success";
        }
    }
    
    if (isset($_POST['revoke_api'])) {
        $id = intval($_POST['api_id']);
        $conn->query("UPDATE api_keys SET status = 0 WHERE id = $id");
        $msg = "API Key Revoked."; $msg_type = "success";
    }
}

$keys = $conn->query("SELECT * FROM api_keys ORDER BY id DESC");
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
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }, colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A' } } } }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 md:p-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">API Credentials</h2>
                        <p class="text-sm text-slate-500 mt-0.5">Manage Access & Secret Keys for External Integrations.</p>
                    </div>
                    <button onclick="document.getElementById('apiModal').classList.remove('hidden')" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-lg flex items-center gap-2">
                        <i class="ph ph-plus text-lg"></i> Create API Key
                    </button>
                </div>

                <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 bg-emerald-50 text-emerald-700 border-emerald-200">
                    <i class="ph ph-check-circle text-xl"></i><span class="text-sm font-medium"><?= $msg ?></span>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-bold uppercase text-[11px]">
                            <tr>
                                <th class="px-6 py-4">Application Name</th>
                                <th class="px-6 py-4">Access Key</th>
                                <th class="px-6 py-4">Secret Key</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <?php while($k = $keys->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-6 py-4 font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($k['app_name']) ?></td>
                                <td class="px-6 py-4 font-mono text-xs text-primary"><?= $k['access_key'] ?></td>
                                <td class="px-6 py-4 font-mono text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="blur-sm select-none">********************************</span>
                                        <button onclick="copyToClipboard('<?= $k['secret_key'] ?>')" class="text-slate-400 hover:text-primary"><i class="ph ph-copy"></i></button>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($k['status'] == 1): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if($k['status'] == 1): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to revoke this key?');">
                                        <input type="hidden" name="api_id" value="<?= $k['id'] ?>">
                                        <button type="submit" name="revoke_api" class="text-red-500 hover:text-red-700 font-bold text-xs">Revoke</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <div id="apiModal" class="fixed inset-0 z-50 hidden bg-slate-900/40 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-darkcard rounded-2xl p-6 w-full max-w-md shadow-2xl">
            <h3 class="font-bold text-lg mb-4 dark:text-white">Create New API Key</h3>
            <form method="POST">
                <label class="block text-xs font-bold text-slate-500 mb-2">Application / Integration Name</label>
                <input type="text" name="app_name" required class="w-full border rounded-xl p-3 mb-6 outline-none focus:border-primary dark:bg-slate-800 dark:border-slate-700 dark:text-white" placeholder="e.g. ERP System">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('apiModal').classList.add('hidden')" class="px-4 py-2 border rounded-xl font-bold">Cancel</button>
                    <button type="submit" name="create_api" class="px-4 py-2 bg-primary text-white rounded-xl font-bold shadow-lg">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => { alert("Secret Key copied to clipboard!"); });
        }
    </script>
</body>
</html>