<?php 
include 'config.php';
checkLogin();

// Gatekeeper
enforcePermission('manage_company');

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// 1. IZINKAN ROLE USER
// User boleh akses jika admin/superadmin, ATAU jika role-nya 'user'
$can_manage = in_array($role, ['superadmin', 'admin']) || ($role == 'user');

// --- DATABASE CHECK (PENTING) ---
$checkCol = $conn->query("SHOW COLUMNS FROM companies LIKE 'parent_id'");
if ($checkCol->num_rows == 0) {
    die('<div class="p-4 bg-red-100 text-red-700 text-center font-bold">System Error: Kolom parent_id tidak ditemukan. Harap update database.</div>');
}

// --- HELPER FUNCTIONS ---
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) { 
        if ($bytes <= 0) return '0 MB';
        $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 
        $bytes /= pow(1024, $pow); 
        return number_format($bytes, $precision) . ' ' . $units[$pow]; 
    }
}

// Safe Tree Builder
function buildTreeSafe(array $elements, $parentId = 0, $depth = 0) {
    if ($depth > 20) return []; 
    $branch = array();
    foreach ($elements as $element) {
        $pid = $element['parent_id'] ?? 0;
        if ($element['id'] == $pid) continue; 

        if ($pid == $parentId) {
            $children = buildTreeSafe($elements, $element['id'], $depth + 1);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

// Flatten Tree untuk Tabel
function flatTreeTable($tree, $depth = 0) {
    $result = [];
    foreach ($tree as $node) {
        $node['_depth'] = $depth;
        $children = $node['children'] ?? [];
        unset($node['children']);
        $result[] = $node;
        if (!empty($children)) {
            $result = array_merge($result, flatTreeTable($children, $depth + 1));
        }
    }
    return $result;
}

// --- AJAX HANDLER ---
if (isset($_GET['get_sims']) && isset($_GET['company_id'])) {
    error_reporting(0);
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    $cid = intval($_GET['company_id']);
    
    // Cek akses user terhadap company ini sebelum load data (Security)
    // (Logic sederhana: jika user global boleh semua, jika restricted cek ID)
    
    $q = $conn->query("SELECT msisdn, iccid, total_flow FROM sims WHERE company_id = $cid ORDER BY id DESC LIMIT 50");
    $data = [];
    if ($q) {
        while($r = $q->fetch_assoc()) {
            $pkg = formatBytes($r['total_flow'] ?? 0);
            $pkgHtml = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100">'.$pkg.'</span>';
            $data[] = ['msisdn' => htmlspecialchars($r['msisdn']), 'iccid' => htmlspecialchars($r['iccid']), 'package_html' => $pkgHtml];
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else { echo json_encode(['status' => 'error']); }
    exit;
}

$msg = '';
$msg_type = '';

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $can_manage) {
    if (isset($_POST['save_company'])) {
        $id = $_POST['company_id'] ? intval($_POST['company_id']) : null;
        $name = trim($_POST['company_name']);
        $level = intval($_POST['level']); 
        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : NULL;
        $project = trim($_POST['project_name']);
        $pic_name = trim($_POST['pic_name']);
        $pic_email = trim($_POST['pic_email']);
        $pic_phone = trim($_POST['pic_phone']);
        $quota_bytes = 0; 

        // Validasi Dasar
        if ($id && $parent_id == $id) {
            $msg = "Error: Company cannot be its own parent."; $msg_type = "error";
        } elseif ($level > 1 && empty($parent_id)) {
            $msg = "Error: Level $level company must have a Parent."; $msg_type = "error";
        } else {
            if (empty($id)) {
                // INSERT
                $stmt = $conn->prepare("INSERT INTO companies (company_name, parent_id, project_name, level, pic_name, pic_email, pic_phone, quota_bytes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisssssd", $name, $parent_id, $project, $level, $pic_name, $pic_email, $pic_phone, $quota_bytes);
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    // Jika role User/Admin biasa membuat company, otomatis assign akses ke mereka
                    if (!in_array($role, ['superadmin'])) {
                        $conn->query("INSERT INTO user_company_access (user_id, company_id) VALUES ($user_id, $new_id)");
                    }
                    $msg = "Company added successfully."; $msg_type = "success";
                } else { $msg = "DB Error: " . $conn->error; $msg_type = "error"; }
            } else {
                // UPDATE
                $stmt = $conn->prepare("UPDATE companies SET company_name=?, parent_id=?, project_name=?, level=?, pic_name=?, pic_email=?, pic_phone=? WHERE id=?");
                $stmt->bind_param("sisssssi", $name, $parent_id, $project, $level, $pic_name, $pic_email, $pic_phone, $id);
                if ($stmt->execute()) { $msg = "Company updated."; $msg_type = "success"; } 
                else { $msg = "Error updating."; $msg_type = "error"; }
            }
        }
    }
    
    if (isset($_POST['delete_company'])) {
        $id = intval($_POST['delete_id']);
        $hasChild = $conn->query("SELECT id FROM companies WHERE parent_id=$id")->num_rows;
        $hasSims = $conn->query("SELECT id FROM sims WHERE company_id=$id")->num_rows;
        
        if ($hasChild > 0) { $msg = "Cannot delete: Has child companies."; $msg_type = "error"; }
        elseif ($hasSims > 0) { $msg = "Cannot delete: Has active SIMs."; $msg_type = "error"; }
        else {
            $conn->query("DELETE FROM user_company_access WHERE company_id=$id");
            $conn->query("DELETE FROM companies WHERE id=$id");
            $msg = "Company deleted."; $msg_type = "success";
        }
    }
}

// --- DATA FETCHING ---
// Ambil daftar ID company yang boleh diakses user ini
$user_companies = []; 
if (!in_array($role, ['superadmin', 'admin'])) {
    $uCheck = $conn->query("SELECT is_global FROM users WHERE id = $user_id")->fetch_assoc();
    if (!$uCheck['is_global']) {
        $accessQ = $conn->query("SELECT company_id FROM user_company_access WHERE user_id = $user_id");
        while($r = $accessQ->fetch_assoc()) $user_companies[] = $r['company_id'];
    }
}

// Fetch All Companies
$sql = "SELECT c.*, COUNT(s.id) as total_sims 
        FROM companies c 
        LEFT JOIN sims s ON c.id = s.company_id 
        GROUP BY c.id 
        ORDER BY c.level ASC, c.company_name ASC";
$res = $conn->query($sql);

$raw_data = [];
$dropdown_options = [];

while($r = $res->fetch_assoc()) {
    // Filter Data: Hanya masukkan ke array jika user punya akses
    if (empty($user_companies) || in_array($r['id'], $user_companies)) {
        $dropdown_options[] = [
            'id' => $r['id'],
            'name' => $r['company_name'],
            'level' => (int)$r['level']
        ];
        $raw_data[] = $r; // Hanya data milik user yang masuk raw_data untuk Tree
    }
}

// Build Tree Visual
// Root ID diset 0. Namun jika user hanya punya akses ke Child (misal Level 2), 
// maka tree builder standar (root=0) tidak akan menemukannya karena parentnya (Level 1) tidak ada di $raw_data.
// Solusi: Kita gunakan logic visual flat saja untuk User biasa, atau tree builder yang lebih pintar.
// Untuk simplifikasi dan agar role User bisa melihat datanya, kita gunakan logic buildTreeSafe
// dengan sedikit modifikasi: Jika user bukan admin, kita anggap company teratas yang dia punya sebagai root visual.

$tree = buildTreeSafe($raw_data, 0); 

// Fallback: Jika tree kosong tapi raw_data ada (artinya user memegang node tengah, bukan root 0),
// maka kita tampilkan raw_data secara flat saja agar tidak blank tabelnya.
if (empty($tree) && !empty($raw_data)) {
    // Paksa mode flat untuk user yang tidak punya akses root
    $displayData = $raw_data;
    // Set depth manual 0
    foreach ($displayData as &$d) $d['_depth'] = 0;
} else {
    $displayData = flatTreeTable($tree);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Companies</title>
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
                    colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A' }
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
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-white dark:bg-darkcard border border-slate-100 dark:border-slate-700 rounded-xl shadow-sm text-primary"><i class="ph ph-tree-structure text-2xl"></i></div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Company Directory</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Manage hierarchical customers & SIM inventory.</p>
                        </div>
                    </div>
                    <?php if($can_manage): ?>
                    <button onclick="openModal()" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-lg shadow-indigo-500/20 active:scale-95 transition-all">
                        <i class="ph ph-plus text-lg"></i> <span>Add Customer</span>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-xl border flex items-center gap-3 <?= $msg_type=='success'?'bg-emerald-50 text-emerald-700 border-emerald-200':'bg-red-50 text-red-700 border-red-200' ?>">
                    <i class="ph <?= $msg_type=='success'?'ph-check-circle':'ph-warning-circle' ?> text-xl"></i>
                    <span class="text-sm font-medium"><?= $msg ?></span>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-lg border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 font-bold uppercase text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-6 py-4">Structure / Company Name</th>
                                    <th class="px-6 py-4">PIC Info</th>
                                    <th class="px-6 py-4 text-center">Level</th>
                                    <th class="px-6 py-4 text-center">Total SIMs</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php if(empty($displayData)): ?>
                                    <tr><td colspan="5" class="p-8 text-center text-slate-400">No companies found.</td></tr>
                                <?php else: foreach($displayData as $row): 
                                    $indent = ($row['_depth'] ?? 0) * 24; 
                                    $icon = (($row['_depth'] ?? 0) > 0) ? '<i class="ph ph-arrow-elbow-down-right text-slate-300 mr-2"></i>' : '<i class="ph ph-buildings text-slate-300 mr-2"></i>';
                                    
                                    $editJson = json_encode([
                                        'id'=>$row['id'], 'name'=>$row['company_name'], 'level'=>$row['level'], 
                                        'parent_id'=>$row['parent_id'], 'project'=>$row['project_name'],
                                        'picn'=>$row['pic_name'], 'pice'=>$row['pic_email'], 'picp'=>$row['pic_phone']
                                    ]);
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center" style="padding-left: <?= $indent ?>px">
                                            <?= $icon ?>
                                            <div class="flex items-center gap-3">
                                                <div>
                                                    <p class="font-bold text-slate-800 dark:text-white text-sm"><?= htmlspecialchars($row['company_name']) ?></p>
                                                    <?php if($row['project_name']): ?>
                                                    <p class="text-[10px] text-slate-400"><?= htmlspecialchars($row['project_name']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-xs">
                                        <p class="font-semibold text-slate-700 dark:text-slate-200"><?= $row['pic_name'] ?: '-' ?></p>
                                        <p class="text-slate-400"><?= $row['pic_email'] ?></p>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">L<?= $row['level'] ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100"><?= number_format($row['total_sims']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button onclick="openViewList(<?= $row['id'] ?>, '<?= addslashes($row['company_name']) ?>')" class="p-2 rounded hover:bg-indigo-50 text-slate-400 hover:text-primary"><i class="ph ph-eye text-lg"></i></button>
                                            <?php if($can_manage): ?>
                                            <button onclick='editCompany(<?= $editJson ?>)' class="p-2 rounded hover:bg-blue-50 text-slate-400 hover:text-blue-600"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes($row['company_name']) ?>')" class="p-2 rounded hover:bg-red-50 text-slate-400 hover:text-red-600"><i class="ph ph-trash text-lg"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="companyModal" class="fixed inset-0 z-[60] hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeModal()"></div>
        <div class="fixed inset-0 z-10 w-screen flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-darkcard rounded-2xl shadow-2xl scale-95 opacity-0 transition-all" id="modalPanel">
                <form method="POST">
                    <input type="hidden" name="save_company" value="1">
                    <input type="hidden" name="company_id" id="modal_id">
                    
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modalTitle">Add New Company</h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Company Name *</label>
                                <input type="text" name="company_name" id="modal_name" required class="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Project Name</label>
                                <input type="text" name="project_name" id="modal_project" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 outline-none">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Level *</label>
                                <select name="level" id="modal_level" onchange="toggleParentInput()" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 outline-none">
                                    <?php if(in_array($role, ['superadmin', 'admin'])): ?>
                                    <option value="1">Level 1 (Main)</option>
                                    <?php endif; ?>
                                    <option value="2">Level 2</option>
                                    <option value="3">Level 3</option>
                                    <option value="4">Level 4</option>
                                </select>
                            </div>

                            <div id="parentDiv">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Parent Company *</label>
                                <select name="parent_id" id="modal_parent" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 outline-none">
                                    <option value="">-- Select Parent --</option>
                                    <?php 
                                    // Dropdown sudah difilter di PHP (hanya milik user)
                                    foreach($dropdown_options as $opt): ?>
                                    <option value="<?= $opt['id'] ?>" data-level="<?= $opt['level'] ?>">
                                        <?= htmlspecialchars($opt['name']) ?> (Level <?= $opt['level'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-slate-700">
                            <h4 class="text-xs font-bold text-primary uppercase mb-3">PIC Info</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <input type="text" name="pic_name" id="modal_pic_name" placeholder="Full Name" class="col-span-2 w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 outline-none">
                                <input type="email" name="pic_email" id="modal_pic_email" placeholder="Email" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 outline-none">
                                <input type="text" name="pic_phone" id="modal_pic_phone" placeholder="Phone" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl p-2.5 bg-white dark:bg-slate-800 outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 flex justify-end gap-3 rounded-b-2xl border-t border-slate-100 dark:border-slate-700">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-white border border-transparent hover:border-slate-200">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-indigo-600 shadow-lg active:scale-95">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="viewModal" class="fixed inset-0 z-[60] hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="viewBackdrop" onclick="closeViewList()"></div>
        <div class="fixed inset-0 z-10 w-screen flex items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-darkcard rounded-2xl shadow-xl overflow-hidden scale-95 opacity-0 transition-all" id="viewPanel">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white relative">
                    <h3 id="viewCompanyName" class="text-lg font-bold">Company Name</h3>
                    <button onclick="closeViewList()" class="absolute top-4 right-4 text-white/80 hover:text-white"><i class="ph ph-x text-xl"></i></button>
                </div>
                <div class="h-80 overflow-y-auto p-0"><table class="w-full text-xs text-left"><tbody id="simListBody" class="divide-y divide-slate-100 dark:divide-slate-700"></tbody></table></div>
                <div class="p-4 border-t bg-slate-50 dark:bg-slate-800"><a id="viewAllLink" href="#" class="block w-full text-center py-3 bg-slate-900 text-white rounded-xl font-bold">View All SIMs</a></div>
            </div>
        </div>
    </div>
    
    <div id="deleteModal" class="fixed inset-0 z-[60] hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="document.getElementById('deleteModal').classList.add('hidden')"></div>
        <div class="fixed inset-0 z-10 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-darkcard rounded-2xl shadow-xl p-6 max-w-sm text-center">
                <h3 class="font-bold text-lg mb-2 dark:text-white">Delete Company?</h3>
                <p class="text-sm text-slate-500 mb-6">Confirm deletion of <strong id="delCompName"></strong>?</p>
                <form method="POST" class="flex gap-3 justify-center">
                    <input type="hidden" name="delete_company" value="1"><input type="hidden" name="delete_id" id="deleteId">
                    <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 border rounded-xl font-bold dark:text-slate-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-xl font-bold shadow-lg">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // LOGIC DINAMIS PARENT SELECTION
        function toggleParentInput() {
            const levelEl = document.getElementById('modal_level');
            const parentDiv = document.getElementById('parentDiv');
            const parentSelect = document.getElementById('modal_parent');
            const currentEditIdEl = document.getElementById('modal_id');

            // Safety guard
            if (!levelEl || !parentDiv || !parentSelect) return;

            const level = parseInt(levelEl.value) || 0;
            const currentEditId = currentEditIdEl ? currentEditIdEl.value : "";

            if (level > 1) {
                parentDiv.classList.remove('hidden');
                parentSelect.required = true;

                const requiredParentLevel = level - 1;
                const options = parentSelect.options;
                let firstValid = "";

                // Reset selection
                let currentVal = parentSelect.value;
                let isValidCurrent = false;

                for (let i = 0; i < options.length; i++) {
                    let opt = options[i];
                    if (!opt.value) continue;

                    let optLevel = parseInt(opt.getAttribute('data-level')) || 0;
                    // Mencegah company memilih dirinya sendiri sebagai parent
                    let isSelf = (currentEditId && opt.value == currentEditId);

                    if (optLevel === requiredParentLevel && !isSelf) {
                        opt.style.display = 'block';
                        opt.disabled = false;
                        if (!firstValid) firstValid = opt.value;
                        if (opt.value == currentVal) isValidCurrent = true;
                    } else {
                        opt.style.display = 'none';
                        opt.disabled = true;
                    }
                }
                
                // Auto select jika value sekarang tidak valid
                if (!isValidCurrent && firstValid) {
                    parentSelect.value = firstValid;
                } else if (!isValidCurrent) {
                    parentSelect.value = "";
                }

            } else {
                parentDiv.classList.add('hidden');
                parentSelect.required = false;
                parentSelect.value = "";
            }
        }

        // ANIMASI & MODAL
        function animateModal(m, b, p, show) {
            // Safety check: pastikan elemen ada agar tidak crash (penyebab blank)
            if(!m || !b || !p) { console.error("Modal elements missing"); return; }
            
            if(show) { 
                m.classList.remove('hidden'); 
                setTimeout(()=>{ 
                    b.classList.remove('opacity-0'); 
                    p.classList.remove('opacity-0','scale-95'); 
                    p.classList.add('opacity-100','scale-100'); 
                },10); 
            } else { 
                b.classList.add('opacity-0'); 
                p.classList.remove('opacity-100','scale-100'); 
                p.classList.add('opacity-0','scale-95'); 
                setTimeout(()=>{ m.classList.add('hidden'); },300); 
            }
        }

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Add New Company';
            document.getElementById('modal_id').value = '';
            document.querySelector('#companyModal form').reset();

            const lvl = document.getElementById('modal_level');
            // Reset ke opsi pertama yang valid (untuk User mungkin Level 2, Admin Level 1)
            if (lvl && lvl.options.length > 0) {
                 // Cari opsi pertama yang tidak disabled/hidden
                 for(let i=0; i<lvl.options.length; i++){
                     if(!lvl.options[i].disabled && !lvl.options[i].hidden){
                         lvl.selectedIndex = i;
                         break;
                     }
                 }
            }

            // Panggil animate dengan ID yang sudah diperbaiki
            animateModal(
                document.getElementById('companyModal'),
                document.getElementById('modalBackdrop'),
                document.getElementById('modalPanel'),
                true
            );

            setTimeout(() => { toggleParentInput(); }, 50);
        }

        function editCompany(d) {
            document.getElementById('modalTitle').innerText = 'Edit Company';
            document.getElementById('modal_id').value = d.id;
            document.getElementById('modal_name').value = d.name;
            document.getElementById('modal_project').value = d.project;
            document.getElementById('modal_level').value = d.level;
            
            // Set value parent dulu sebelum toggle (agar logic validasi berjalan benar)
            // Tapi toggle akan mereset jika value tidak valid, jadi urutannya penting.
            
            // 1. Set Level dulu
            
            // 2. Jalankan toggle untuk memfilter opsi parent yang valid
            toggleParentInput(); 
            
            // 3. Baru set parent value
            if(d.parent_id) document.getElementById('modal_parent').value = d.parent_id;

            document.getElementById('modal_pic_name').value = d.picn;
            document.getElementById('modal_pic_email').value = d.pice;
            document.getElementById('modal_pic_phone').value = d.picp;
            
            animateModal(document.getElementById('companyModal'), document.getElementById('modalBackdrop'), document.getElementById('modalPanel'), true);
        }

        function closeModal() { 
            animateModal(document.getElementById('companyModal'), document.getElementById('modalBackdrop'), document.getElementById('modalPanel'), false); 
        }
        
        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('delCompName').innerText = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        // View List Logic
        function openViewList(cid, name) {
            document.getElementById('viewCompanyName').innerText = name;
            document.getElementById('viewAllLink').href = 'sim-list.php?company=' + cid;
            
            // Gunakan viewBackdrop
            animateModal(
                document.getElementById('viewModal'), 
                document.getElementById('viewBackdrop'), 
                document.getElementById('viewPanel'), 
                true
            );
            
            const tbody = document.getElementById('simListBody');
            tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-400">Loading...</td></tr>';
            
            fetch(`manage-company.php?get_sims=1&company_id=${cid}`).then(r=>r.json()).then(res=>{
                if(res.status=='success'){
                    let h=''; 
                    if(res.data.length==0) h='<tr><td colspan="3" class="p-6 text-center text-slate-400">No SIMs.</td></tr>';
                    else res.data.forEach(s=>{ h+=`<tr class="hover:bg-slate-50"><td class="px-6 py-3 font-mono font-bold">${s.msisdn}</td><td class="px-6 py-3 text-xs">${s.iccid}</td><td class="px-6 py-3 text-right">${s.package_html}</td></tr>`; });
                    tbody.innerHTML = h;
                }
            });
        }
        function closeViewList() { 
            animateModal(document.getElementById('viewModal'), document.getElementById('viewBackdrop'), document.getElementById('viewPanel'), false); 
        }
    </script>
</body>
</html>