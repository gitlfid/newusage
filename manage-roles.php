<?php 
include 'config.php';
checkLogin();

// // SECURITY: Hanya Superadmin
// if($_SESSION['role'] !== 'superadmin') {
//     header("Location: dashboard"); exit();
// }

enforcePermission('manage_roles');

$msg = '';
$msg_type = '';

// --- 1. DEFINISI MENU SISTEM (LENGKAP) ---
$system_menus = [
    'dashboard'     => ['label' => 'Dashboard', 'icon' => 'ph-squares-four'],
    'sim_list'      => ['label' => 'SIM Monitor', 'icon' => 'ph-sim-card'],
    'sim_upload'    => ['label' => 'SIM Upload', 'icon' => 'ph-upload-simple'],
    'manage_users'  => ['label' => 'User Management', 'icon' => 'ph-users'],
    'manage_roles'  => ['label' => 'Role Management', 'icon' => 'ph-lock-key'],
    'manage_company'=> ['label' => 'Company Management', 'icon' => 'ph-buildings'],
    'settings'      => ['label' => 'App Settings', 'icon' => 'ph-gear'],
];

// --- 2. HANDLE SAVE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_permissions'])) {
    $target_role = $_POST['target_role'];
    $selected_menus = $_POST['menus'] ?? []; 

    // Reset permission lama
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role = ?");
    $stmt->bind_param("s", $target_role);
    $stmt->execute();

    // Insert permission baru
    if (!empty($selected_menus)) {
        $sql_parts = [];
        foreach ($selected_menus as $menu) {
            if(array_key_exists($menu, $system_menus)) {
                $sql_parts[] = "('$target_role', '$menu')";
            }
        }
        if(!empty($sql_parts)) {
            $sql = "INSERT INTO role_permissions (role, menu_key) VALUES " . implode(',', $sql_parts);
            if ($conn->query($sql)) {
                $msg = "Permissions for <b>".ucfirst($target_role)."</b> updated successfully.";
                $msg_type = "success";
            }
        }
    } else {
        $msg = "All permissions revoked for <b>".ucfirst($target_role)."</b>.";
        $msg_type = "warning";
    }
}

// --- 3. GET CURRENT PERMISSIONS ---
$permissions = [];
$q = $conn->query("SELECT * FROM role_permissions");
while($row = $q->fetch_assoc()) {
    $permissions[$row['role']][] = $row['menu_key'];
}

$roles_to_manage = ['admin', 'user']; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Roles</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { 
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: { primary: '#4F46E5', darkcard: '#24303F', darkbg: '#1A222C' },
                    animation: { 'fade-in-up': 'fadeInUp 0.3s ease-out forwards' },
                    keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans">
    
    <div id="toast" class="fixed top-5 right-5 z-[70] transform transition-all duration-300 translate-x-full opacity-0">
        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 border-l-4 border-emerald-500 shadow-xl rounded-lg p-4 pr-8">
            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-full text-emerald-600"><i class="ph ph-check-circle text-xl"></i></div>
            <div>
                <h4 class="font-bold text-slate-800 dark:text-white text-sm">Success</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5" id="toastMsg">Action completed.</p>
            </div>
            <button onclick="hideToast()" class="absolute top-2 right-2 text-slate-400 hover:text-slate-600"><i class="ph ph-x"></i></button>
        </div>
    </div>

    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 md:p-8">
                
                <div class="mb-8 animate-fade-in-up">
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-primary">
                            <i class="ph ph-lock-key text-2xl"></i>
                        </div>
                        Role & Permissions
                    </h2>
                    <p class="text-sm text-slate-500 mt-1 ml-14">Configure access rights for each user role.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 animate-fade-in-up" style="animation-delay: 0.1s;">
                    
                    <?php foreach($roles_to_manage as $role): 
                        $roleColor = ($role === 'admin') ? 'text-blue-600 bg-blue-50 dark:bg-blue-900/20' : 'text-slate-600 bg-slate-100 dark:bg-slate-700/50';
                        $current_perms = $permissions[$role] ?? [];
                    ?>
                    
                    <div class="bg-white dark:bg-darkcard rounded-2xl shadow-lg border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col h-full">
                        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/30">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center <?= $roleColor ?>">
                                    <i class="ph ph-shield-check text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white capitalize"><?= $role ?></h3>
                                    <p class="text-xs text-slate-400">Manage permissions</p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" class="flex-1 flex flex-col">
                            <input type="hidden" name="save_permissions" value="1">
                            <input type="hidden" name="target_role" value="<?= $role ?>">
                            
                            <div class="p-6 flex-1 space-y-3">
                                <?php foreach($system_menus as $key => $menu): 
                                    $isChecked = in_array($key, $current_perms) ? 'checked' : '';
                                ?>
                                <label class="flex items-center justify-between group p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border border-transparent hover:border-slate-100 dark:hover:border-slate-700 cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 rounded-lg bg-white dark:bg-slate-800 shadow-sm border border-slate-100 dark:border-slate-700 text-slate-500 dark:text-slate-400 group-hover:text-primary transition-colors">
                                            <i class="ph <?= $menu['icon'] ?> text-lg"></i>
                                        </div>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= $menu['label'] ?></span>
                                    </div>
                                    
                                    <div class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="menus[]" value="<?= $key ?>" class="sr-only peer" <?= $isChecked ?>>
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="px-6 py-4 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-700">
                                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold text-sm hover:opacity-90 transition-opacity shadow-lg shadow-slate-500/20 active:scale-95">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>

                    <div class="lg:col-span-2 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                                <i class="ph ph-crown text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg">Superadmin Access</h3>
                                <p class="text-indigo-100 text-sm">Superadmins have full access to all menus and features by default.</p>
                            </div>
                        </div>
                        <i class="ph ph-lock-key-open text-4xl opacity-50"></i>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function hideToast() {
            const t = document.getElementById('toast');
            t.classList.add('translate-x-full', 'opacity-0');
        }
        
        <?php if($msg): ?>
        setTimeout(() => {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').innerHTML = "<?= $msg ?>";
            t.classList.remove('translate-x-full', 'opacity-0');
            setTimeout(hideToast, 4000);
        }, 100);
        <?php endif; ?>
    </script>
</body>
</html>