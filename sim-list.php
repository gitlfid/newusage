<?php 
include 'config.php';
checkLogin();

enforcePermission('sim_list');

// --- 0. PREPARATION ---
$user_id = $_SESSION['user_id'];

// --- Helper Functions ---
if (!function_exists('formatToDefaultMB')) {
    function formatToDefaultMB($bytes) { 
        if ($bytes <= 0) return '0.00 MB';
        $mb = $bytes / 1048576; 
        return number_format($mb, 2) . ' MB';
    }
}

if (!function_exists('getRealtimeStatusBadge')) {
    function getRealtimeStatusBadge($status) {
        if ($status == '2') return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active</span>';
        return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-600"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Inactive</span>';
    }
}

// --- 1. DATA ACCESS CONTROL ---
$allowed_comps = getClientIdsForUser($user_id);
$company_condition = "";

if ($allowed_comps === 'NONE') {
    $company_condition = " AND 1=0 "; 
} elseif (is_array($allowed_comps)) {
    $ids_str = implode(',', $allowed_comps);
    $company_condition = " AND sims.company_id IN ($ids_str) ";
} 

// --- 2. HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // A. UPDATE TAGS
    if (isset($_POST['action']) && $_POST['action'] == 'update_tags') {
        $ids_raw = $_POST['sim_ids']; 
        // Filter ID menjadi integer untuk keamanan
        $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
        $clean_tags = trim($_POST['tags_final']); 
        
        if(!empty($ids)) {
            $types = str_repeat('i', count($ids));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE sims SET tags = ? WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $params = array_merge([$clean_tags], $ids);
            $stmt->bind_param('s' . $types, ...$params);
            $stmt->execute();
        }
        header("Location: sim-list.php?msg=tags_updated");
        exit();
    }

    // B. UPDATE PROJECT
    if (isset($_POST['action']) && $_POST['action'] == 'update_project') {
        $project_name = trim($_POST['project_name']);
        $ids_raw = $_POST['sim_ids'];
        $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
        
        if(!empty($ids)) {
            $val = !empty($project_name) ? $project_name : NULL;
            $types = str_repeat('i', count($ids));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE sims SET custom_project = ? WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $params = array_merge([$val], $ids);
            $stmt->bind_param('s' . $types, ...$params);
            $stmt->execute();
        }
        header("Location: sim-list.php?msg=project_updated");
        exit();
    }

    // C. TRANSFER SIM
    if (isset($_POST['action']) && $_POST['action'] == 'transfer_sim') {
        $target_cid = intval($_POST['target_company_id']);
        $transfer_type = $_POST['transfer_type']; 

        if ($transfer_type == 'selection') {
            $ids_raw = $_POST['sim_ids'];
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (!empty($ids)) {
                $types = str_repeat('i', count($ids));
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "UPDATE sims SET company_id = ? WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $params = array_merge([$target_cid], $ids);
                $stmt->bind_param('i' . $types, ...$params);
                $stmt->execute();
            }
        } elseif ($transfer_type == 'upload') {
            $msisdns = json_decode($_POST['msisdn_json'], true);
            if (!empty($msisdns)) {
                $types = str_repeat('s', count($msisdns));
                $placeholders = implode(',', array_fill(0, count($msisdns), '?'));
                $sql = "UPDATE sims SET company_id = ? WHERE msisdn IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $params = array_merge([$target_cid], $msisdns);
                $stmt->bind_param('i' . $types, ...$params);
                $stmt->execute();
            }
        }
        header("Location: sim-list.php?msg=transfer_success");
        exit();
    }
}

// --- 3. FILTERS & QUERY ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = isset($_GET['size']) ? intval($_GET['size']) : 10;
$offset = ($page - 1) * $pageSize;

$s_keyword = $_GET['search'] ?? '';
$f_company = $_GET['company'] ?? '';
$f_project = $_GET['project'] ?? '';
$f_invoice = $_GET['invoice'] ?? '';
$f_batch   = $_GET['batch'] ?? ''; 
$f_level   = $_GET['level'] ?? '';

$where = "WHERE 1=1 " . $company_condition;

if (!empty($s_keyword)) {
    $raw_terms = preg_split('/[\s,]+/', trim($s_keyword), -1, PREG_SPLIT_NO_EMPTY);
    $terms = array_map(function($t) use ($conn) { return $conn->real_escape_string(trim($t)); }, $raw_terms);

    if (count($terms) === 1) {
        $term = $terms[0];
        $where .= " AND (sims.iccid LIKE '%$term%' OR sims.imsi LIKE '%$term%' OR sims.msisdn LIKE '%$term%' OR sims.sn LIKE '%$term%')";
    } elseif (count($terms) > 1) {
        $inList = "'" . implode("','", $terms) . "'";
        $where .= " AND (sims.iccid IN ($inList) OR sims.imsi IN ($inList) OR sims.msisdn IN ($inList) OR sims.sn IN ($inList))";
    }
}

if ($f_company) {
    $safeComp = $conn->real_escape_string($f_company);
    $where .= " AND sims.company_id = '$safeComp'";
}
if ($f_invoice) {
    $safeInv = $conn->real_escape_string($f_invoice);
    $where .= " AND sims.invoice_number = '$safeInv'";
}
if ($f_batch) {
    $safeBatch = $conn->real_escape_string($f_batch);
    $where .= " AND sims.batch = '$safeBatch'";
}
if ($f_level) {
    $where .= " AND companies.level = '$f_level'";
}
if ($f_project) {
    $safeProj = $conn->real_escape_string($f_project);
    $where .= " AND (sims.custom_project = '$safeProj' OR (sims.custom_project IS NULL AND companies.project_name = '$safeProj'))";
}

$totalRes = $conn->query("SELECT COUNT(*) as total FROM sims LEFT JOIN companies ON sims.company_id = companies.id $where");
$totalRecords = $totalRes->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $pageSize);

$sql = "SELECT sims.*, companies.company_name, companies.project_name as default_project, companies.level 
        FROM sims LEFT JOIN companies ON sims.company_id = companies.id $where 
        ORDER BY sims.id DESC LIMIT $offset, $pageSize";
$result = $conn->query($sql);

$compWhere = is_array($allowed_comps) ? "WHERE id IN (" . implode(',', $allowed_comps) . ")" : ($allowed_comps === 'NONE' ? "WHERE 1=0" : "");
$compArr = []; 
$cQ = $conn->query("SELECT id, company_name FROM companies $compWhere ORDER BY company_name");
while($r = $cQ->fetch_assoc()) $compArr[] = $r;

$projArr = []; 
$pQ = $conn->query("SELECT DISTINCT IFNULL(custom_project, project_name) as p_name FROM sims LEFT JOIN companies ON sims.company_id = companies.id $where HAVING p_name IS NOT NULL AND p_name != '' ORDER BY p_name");
while($r = $pQ->fetch_assoc()) $projArr[] = $r['p_name'];

// FETCH INVOICE
$invoiceArr = [];
$invSql = "SELECT DISTINCT invoice_number FROM sims LEFT JOIN companies ON sims.company_id = companies.id WHERE invoice_number IS NOT NULL AND invoice_number != '' " . $company_condition;
if ($f_company) {
    $safeCompFilter = $conn->real_escape_string($f_company);
    $invSql .= " AND sims.company_id = '$safeCompFilter'";
}
$invSql .= " ORDER BY invoice_number DESC";
$iQ = $conn->query($invSql);
while($r = $iQ->fetch_assoc()) $invoiceArr[] = $r['invoice_number'];

// FETCH BATCH
$batchArr = [];
$bSql = "SELECT DISTINCT batch FROM sims LEFT JOIN companies ON sims.company_id = companies.id WHERE batch IS NOT NULL AND batch != '' " . $company_condition;
if ($f_company) {
    $safeCompFilter = $conn->real_escape_string($f_company);
    $bSql .= " AND sims.company_id = '$safeCompFilter'";
}
$bSql .= " ORDER BY batch DESC";
$bQ = $conn->query($bSql);
while($r = $bQ->fetch_assoc()) $batchArr[] = $r['batch'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIM Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#4F46E5', darkcard: '#24303F', darkbg: '#1A222C' },
                    animation: { 'fade-in-up': 'fadeInUp 0.3s ease-out forwards' },
                    keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } }
                }
            }
        }
    </script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        .sticky-col { position: sticky !important; z-index: 20; background-color: #ffffff !important; background-clip: padding-box; }
        .dark .sticky-col { background-color: #24303F !important; }
        th.sticky-col { z-index: 30 !important; background-color: #F8FAFC !important; }
        .dark th.sticky-col { background-color: #1F2937 !important; }
        
        .sticky-edge-shadow {
            box-shadow: 4px 0 8px -4px rgba(0,0,0,0.15) !important;
            border-right: 1px solid #e2e8f0 !important;
            clip-path: inset(0px -15px 0px 0px);
        }
        .dark .sticky-edge-shadow {
            box-shadow: 8px 0 12px -4px rgba(0,0,0,0.5) !important;
            border-right: 1px solid #374151 !important;
        }
        
        tr:hover td.sticky-col { background-color: #F8FAFC !important; }
        .dark tr:hover td.sticky-col { background-color: #374151 !important; }
        
        #colManager { z-index: 100 !important; }
        th, td { transition: background-color 0.15s ease-in-out; }

        .tag-input-container { display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.5rem; align-items: center; min-height: 45px; }
        .tag-chip { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; background-color: #EEF2FF; color: #4F46E5; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .dark .tag-chip { background-color: rgba(79, 70, 229, 0.2); color: #818CF8; }
        .tag-input-field { flex: 1; min-width: 100px; outline: none; background: transparent; font-size: 0.875rem; color: #334155; }
        .dark .tag-input-field { color: #F1F5F9; }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans overflow-hidden">
    
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
                
                <div class="flex flex-col sm:flex-row justify-between items-end sm:items-center gap-4 mb-6 animate-fade-in-up">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            SIM Management
                            <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-primary dark:bg-indigo-900/30 dark:text-indigo-300 text-xs font-bold border border-indigo-100 dark:border-indigo-800"><?= number_format($totalRecords) ?></span>
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Monitor usage, manage tags, and configure projects.</p>
                    </div>
                    
                    <div id="bulkActionBar" class="hidden fixed bottom-8 left-1/2 -translate-x-1/2 z-[60] bg-slate-900/90 backdrop-blur-md dark:bg-white/90 text-white dark:text-slate-900 px-6 py-3 rounded-full shadow-2xl items-center gap-6 animate-fade-in-up transition-all border border-white/10 dark:border-slate-200">
                        <div class="flex items-center gap-2">
                            <span class="bg-indigo-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-md" id="selectedCount">0</span>
                            <span class="text-sm font-bold">Selected</span>
                        </div>
                        <div class="h-4 w-px bg-slate-700 dark:bg-slate-300"></div>
                        <button onclick="openBulkTagModal()" class="flex items-center gap-2 text-sm font-medium hover:text-indigo-400 dark:hover:text-indigo-600 transition-colors"><i class="ph ph-tag"></i> Set Tags</button>
                        <button onclick="openBulkProjectModal()" class="flex items-center gap-2 text-sm font-medium hover:text-indigo-400 dark:hover:text-indigo-600 transition-colors"><i class="ph ph-briefcase"></i> Set Project</button>
                        <button onclick="openTransferModal()" class="flex items-center gap-2 text-sm font-medium hover:text-indigo-400 dark:hover:text-indigo-600 transition-colors"><i class="ph ph-arrows-left-right"></i> Transfer</button>
                        <div class="h-4 w-px bg-slate-700 dark:bg-slate-300"></div>
                        <button onclick="document.getElementById('selectAll').click()" class="text-slate-400 hover:text-white dark:hover:text-slate-600 transition-colors"><i class="ph ph-x text-lg"></i></button>
                    </div>
                </div>

                <div class="relative z-10 bg-white dark:bg-darkcard p-5 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <form method="GET" id="filterForm" class="flex flex-wrap gap-4 items-end">
                        <input type="hidden" name="size" value="<?= $pageSize ?>">
                        
                        <div class="flex-grow min-w-[200px] max-w-[300px]">
                            <label class="block mb-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Search (Multiple)</label>
                            <div class="relative group">
                                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                                <input type="text" name="search" id="searchInput" onkeydown="handleSearchEnter(event)" value="<?= htmlspecialchars($s_keyword) ?>" placeholder="ICCID, IMSI, MSISDN..." class="w-full h-[42px] pl-9 pr-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all shadow-sm">
                            </div>
                        </div>

                        <div class="flex-grow min-w-[150px] max-w-[250px]">
                            <label class="block mb-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Company</label>
                            <select name="company" onchange="this.form.submit()" class="w-full h-[42px] px-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all shadow-sm cursor-pointer truncate">
                                <option value="">All Companies</option>
                                <?php foreach($compArr as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $f_company == $c['id'] ? 'selected' : '' ?>><?= $c['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex-grow min-w-[80px] max-w-[200px]">
                            <label class="block mb-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Batch</label>
                            <select name="batch" class="w-full h-[42px] px-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all shadow-sm cursor-pointer truncate">
                                <option value="">All Batches</option>
                                <?php foreach($batchArr as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>" <?= $f_batch === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex-grow min-w-[120px] max-w-[200px]">
                            <label class="block mb-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Invoice No</label>
                            <select name="invoice" class="w-full h-[42px] px-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white transition-all shadow-sm cursor-pointer truncate">
                                <option value="">All Invoices</option>
                                <?php foreach($invoiceArr as $inv): ?>
                                    <option value="<?= $inv ?>" <?= $f_invoice == $inv ? 'selected' : '' ?>><?= $inv ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="min-w-[120px]">
                            <label class="block mb-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Level</label>
                            <div class="flex bg-slate-100 dark:bg-slate-800 rounded-xl p-1 border border-slate-200 dark:border-slate-700 h-[42px]">
                                <?php foreach(['' => 'All', '1'=>'L1', '2'=>'L2', '3'=>'L3'] as $val => $lbl): 
                                    $active = ((string)$f_level === (string)$val) ? 'bg-white dark:bg-slate-600 text-primary dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700';
                                ?>
                                <label class="flex-1 cursor-pointer relative h-full">
                                    <input type="radio" name="level" value="<?= $val ?>" class="sr-only" <?= ((string)$f_level === (string)$val) ? 'checked' : '' ?> onclick="this.form.submit()">
                                    <div class="h-full flex items-center justify-center rounded-lg text-xs font-bold transition-all <?= $active ?>"><?= $lbl ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <div class="relative group">
                                <button type="button" onclick="document.getElementById('colManager').classList.toggle('hidden')" class="h-[42px] px-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 shadow-sm transition-colors flex items-center gap-2">
                                    <i class="ph ph-columns text-lg"></i> Cols
                                </button>
                                <div id="colManager" class="hidden absolute right-0 top-12 w-64 bg-white dark:bg-darkcard rounded-xl shadow-2xl border border-slate-100 dark:border-slate-700 p-4 z-[60] animate-in fade-in zoom-in-95 origin-top-right">
                                    <div class="flex justify-between items-center mb-3">
                                        <h4 class="text-xs font-bold uppercase text-slate-500">Columns</h4>
                                        <button type="button" onclick="resetColumnConfig()" class="text-[10px] text-primary hover:underline">Reset</button>
                                    </div>
                                    <div id="colListContainer" class="flex flex-col gap-1 max-h-[250px] overflow-y-auto no-scrollbar"></div>
                                </div>
                            </div>
                            
                            <div class="relative h-[42px] w-[80px]">
                                <select id="unitSelector" onchange="updateDataUnits()" class="appearance-none w-full h-full pl-3 pr-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-600 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary outline-none cursor-pointer">
                                    <option value="KB">KB</option>
                                    <option value="MB" selected>MB</option>
                                    <option value="GB">GB</option>
                                </select>
                                <i class="ph ph-caret-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                            </div>

                            <button type="submit" id="btnFilter" class="h-[42px] w-[42px] bg-primary hover:bg-indigo-600 text-white rounded-xl shadow-lg shadow-indigo-500/20 flex items-center justify-center transition-all active:scale-95 flex-shrink-0">
                                <i class="ph ph-funnel text-xl"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-lg shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="overflow-x-auto relative">
                        <table id="mainTable" class="w-full text-left border-collapse min-w-[1800px]">
                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 text-[11px] uppercase font-bold tracking-wider">
                                <tr id="tableHeaderRow">
                                    <th data-col="checkbox" class="px-4 py-4 text-center border-r border-slate-100 dark:border-slate-700/50">
                                        <input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-700 dark:border-slate-600 cursor-pointer">
                                    </th>
                                    <th data-col="tags" class="px-4 py-4">Tags</th>
                                    <th data-col="msisdn" class="px-4 py-4">MSISDN</th>
                                    <th data-col="customer" class="px-4 py-4">Customer</th>
                                    <th data-col="level" class="px-4 py-4 text-center">Level</th>
                                    <th data-col="batch" class="px-4 py-4">Batch</th>
                                    <th data-col="card_type" class="px-4 py-4">Card Type</th>
                                    <th data-col="expired_date" class="px-4 py-4">Expired Date</th>
                                    <th data-col="invoice" class="px-4 py-4">Invoice No</th>
                                    <th data-col="project" class="px-4 py-4">Project</th>
                                    <th data-col="imsi" class="px-4 py-4">IMSI</th>
                                    <th data-col="iccid" class="px-4 py-4">ICCID</th>
                                    <th data-col="sn" class="px-4 py-4">SN</th>
                                    <th data-col="package" class="px-4 py-4 text-center">Package</th>
                                    <th data-col="usage" class="px-4 py-4 min-w-[180px]">Usage</th>
                                    <th data-col="action" class="px-4 py-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-700 text-xs text-slate-600 dark:text-slate-300">
                                <?php if($result->num_rows > 0): while($row = $result->fetch_assoc()): 
                                    // PENGGUNAAN ID SEBAGAI IDENTITAS UTAMA AGAR TIDAK BUG SAAT ICCID KOSONG
                                    $row_id = $row['id'];
                                    
                                    $companyName = $row['company_name'] ?? 'Unknown';
                                    $displayProject = !empty($row['custom_project']) ? $row['custom_project'] : $row['default_project'];
                                    $usedRaw = floatval($row['used_flow']??0);
                                    $totalRaw = floatval($row['total_flow']??0);
                                    $pct = ($totalRaw > 0) ? ($usedRaw / $totalRaw) * 100 : 0;
                                    $barColor = ($pct > 90) ? 'bg-red-500' : (($pct > 70) ? 'bg-yellow-500' : 'bg-emerald-500');
                                ?>
                                <tr class="group hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors duration-200">
                                    <td data-col="checkbox" class="px-4 py-3 text-center border-r border-slate-100 dark:border-slate-700/50">
                                        <input type="checkbox" name="sim_ids[]" value="<?= $row_id ?>" class="sim-check w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-700 dark:border-slate-600 cursor-pointer">
                                    </td>
                                    <td data-col="tags" class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5 items-center w-full">
                                            <?php if(!empty($row['tags'])): ?>
                                                <?php foreach(array_slice(array_filter(explode(',', $row['tags'])), 0, 3) as $tag): ?>
                                                    <span class="px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-800 text-[10px] font-medium text-indigo-600 dark:text-indigo-400 truncate max-w-[40px]"><?= trim($tag) ?></span>
                                                <?php endforeach; ?>
                                                <?php if(count(explode(',', $row['tags'])) > 3): ?>
                                                    <span class="text-[9px] text-slate-400 font-medium">+<?= count(explode(',', $row['tags']))-3 ?></span>
                                                <?php endif; ?>
                                                <button onclick="openTagModal('<?= $row_id ?>', '<?= htmlspecialchars($row['tags'] ?? '') ?>')" class="w-5 h-5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
                                                    <i class="ph ph-pencil-simple text-sm"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="openTagModal('<?= $row_id ?>', '')" class="flex items-center gap-1 text-[10px] text-slate-400 hover:text-primary border border-dashed border-slate-300 dark:border-slate-600 rounded px-2 py-0.5 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                                    <i class="ph ph-plus"></i> Add
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-col="msisdn" class="px-4 py-3 font-mono font-bold text-primary dark:text-indigo-400 select-all">
                                        <a href="sim-detail?id=<?= $row_id ?>" class="hover:underline">
                                            <?= htmlspecialchars($row['msisdn'] ?? '-') ?>
                                        </a>
                                    </td>
                                    <td data-col="customer" class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate font-medium text-slate-700 dark:text-slate-200 max-w-[210px]" title="<?= $companyName ?>"><?= $companyName ?></span>
                                        </div>
                                    </td>
                                    <td data-col="level" class="px-4 py-3 text-center">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">L<?= $row['level'] ?? '1' ?></span>
                                    </td>
                                    
                                    <td data-col="batch" class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                        <?php if(!empty($row['batch'])): ?>
                                            <span class="px-2 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded font-mono text-[10px] font-medium border border-slate-200 dark:border-slate-700"><?= htmlspecialchars($row['batch']) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>

                                    <td data-col="card_type" class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                        <?php if(!empty($row['card_type'])): ?>
                                            <span class="px-2 py-1 bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 rounded font-medium text-[10px] border border-purple-200 dark:border-purple-800"><?= htmlspecialchars($row['card_type']) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>

                                    <td data-col="expired_date" class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                        <?php if(!empty($row['expired_date'])): ?>
                                            <span class="text-xs font-medium"><?= date('d M Y', strtotime($row['expired_date'])) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>

                                    <td data-col="invoice" class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['invoice_number'] ?? '-') ?></td>
                                    <td data-col="project" class="px-4 py-3">
                                        <div class="flex items-center gap-1.5 max-w-[140px]">
                                            <button onclick="openSingleProjectModal('<?= $row_id ?>', '<?= htmlspecialchars($displayProject) ?>')" class="text-slate-400 hover:text-primary"><i class="ph ph-pencil-simple text-xs"></i></button>
                                            <span class="truncate font-medium text-slate-600 dark:text-slate-400"><?= htmlspecialchars($displayProject ?: '-') ?></span>
                                        </div>
                                    </td>
                                    <td data-col="imsi" class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['imsi'] ?? '-') ?></td>
                                    <td data-col="iccid" class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['iccid'] ?? '-') ?></td>
                                    <td data-col="sn" class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($row['sn'] ?? '-') ?></td>
                                    
                                    <td data-col="package" class="px-4 py-3 text-center">
                                        <span class="font-bold text-xs text-slate-700 dark:text-slate-200 whitespace-nowrap dynamic-data" data-bytes="<?= $totalRaw ?>"><?= formatToDefaultMB($totalRaw) ?></span>
                                    </td>
                                    <td data-col="usage" class="px-4 py-3">
                                        <div class="w-full">
                                            <div class="flex justify-between items-end mb-1">
                                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 dynamic-data whitespace-nowrap" data-bytes="<?= $usedRaw ?>"><?= formatToDefaultMB($usedRaw) ?></span>
                                                <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap">/ <span class="dynamic-data" data-bytes="<?= $totalRaw ?>"><?= formatToDefaultMB($totalRaw) ?></span></span>
                                            </div>
                                            <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden border border-slate-200 dark:border-slate-600">
                                                <div class="h-full <?= $barColor ?> rounded-full" style="width: <?= min($pct, 100) ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-col="action" class="px-4 py-3 text-center">
                                        <a href="sim-detail?id=<?= $row_id ?>" class="p-2 rounded-lg text-slate-400 hover:text-primary hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all inline-block"><i class="ph ph-caret-right text-lg"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="16" class="py-12 text-center text-slate-500">No SIM cards found matching your filters.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-darkcard flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-2 text-sm text-slate-500">
                            <span>Show</span>
                            <select onchange="changePageSize(this.value)" class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-xs font-bold focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                <option value="10" <?= $pageSize==10?'selected':'' ?>>10</option>
                                <option value="50" <?= $pageSize==50?'selected':'' ?>>50</option>
                                <option value="100" <?= $pageSize==100?'selected':'' ?>>100</option>
                            </select>
                            <span>rows</span>
                        </div>
                        <div class="flex gap-1.5">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page-1 ?>&size=<?= $pageSize ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"><i class="ph ph-caret-left"></i></a>
                            <?php endif; ?>
                            <span class="px-4 h-8 flex items-center justify-center rounded-lg bg-primary text-white text-xs font-bold shadow-lg shadow-indigo-500/30">Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page+1 ?>&size=<?= $pageSize ?>" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"><i class="ph ph-caret-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
            </main>
        </div>
    </div>

    <div id="tagModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-darkcard text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 scale-95" id="modalPanel">
                    <form method="POST" id="tagForm">
                        <input type="hidden" name="action" value="update_tags">
                        <input type="hidden" name="sim_ids" id="modal_tag_ids">
                        <input type="hidden" name="tags_final" id="tags_final"> 
                        <div class="px-6 py-6">
                            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2" id="tagModalTitle">Manage Tags</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Type tag name and press Enter to add. Click (x) to remove.</p>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Tags</label>
                            <div class="tag-input-container w-full border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-800 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
                                <input type="text" id="tagInputText" class="tag-input-field p-2 bg-transparent outline-none text-sm placeholder-slate-400" placeholder="Type & hit Enter...">
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700">
                            <button type="button" onclick="closeModal('tagModal')" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                            <button type="button" onclick="submitTagForm()" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-indigo-600 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">Save Tags</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="projectModal" class="fixed inset-0 z-50 hidden"><div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="projBackdrop"></div><div class="fixed inset-0 z-10 w-screen overflow-y-auto"><div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0"><div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-darkcard text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 scale-95" id="projPanel">
        <form method="POST">
            <input type="hidden" name="action" value="update_project">
            <input type="hidden" name="sim_ids" id="modal_project_ids">
            <div class="px-6 py-6">
                <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-primary mb-4"><i class="ph ph-briefcase text-2xl"></i></div>
                <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2" id="projectModalTitle">Set Project</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Enter new project name.</p>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Project Name</label>
                <input type="text" name="project_name" id="modal_project_input" class="w-full border border-slate-200 dark:border-slate-700 rounded-xl p-3 bg-slate-50 dark:bg-slate-800 dark:text-white focus:border-primary outline-none transition-all">
                <p class="text-[10px] text-slate-400 mt-2">* Leave empty to reset.</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('projectModal')" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-indigo-600 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">Update Project</button>
            </div>
        </form>
    </div></div></div></div>

    <div id="transferModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="transBackdrop"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-darkcard text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 scale-95" id="transPanel">
                    <form method="POST" id="transferForm">
                        <input type="hidden" name="action" value="transfer_sim">
                        <input type="hidden" name="transfer_type" id="transfer_type" value="selection">
                        <input type="hidden" name="sim_ids" id="transfer_sim_ids">
                        <input type="hidden" name="msisdn_json" id="transfer_msisdn_json">

                        <div class="px-6 py-6 border-b border-slate-100 dark:border-slate-700">
                            <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                <i class="ph ph-arrows-left-right text-indigo-500"></i> Transfer SIM Cards
                            </h3>
                        </div>

                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 flex gap-2">
                            <button type="button" onclick="setTransferTab('selection')" id="tab-selection" class="flex-1 py-2 text-sm font-bold rounded-lg transition-all bg-white shadow-sm text-primary">From Selection</button>
                            <button type="button" onclick="setTransferTab('upload')" id="tab-upload" class="flex-1 py-2 text-sm font-bold rounded-lg transition-all text-slate-500 hover:bg-white/50">By File Upload</button>
                        </div>

                        <div class="p-6">
                            <div id="content-selection">
                                <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
                                    You are about to transfer <strong id="transferCountDisplay" class="text-indigo-600">0</strong> selected SIM cards.
                                </p>
                            </div>

                            <div id="content-upload" class="hidden">
                                <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-6 text-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors relative">
                                    <input type="file" id="transferFile" accept=".xlsx, .xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="handleTransferFile(this)">
                                    <i class="ph ph-file-xls text-3xl text-slate-400 mb-2"></i>
                                    <p class="text-sm font-bold text-slate-700 dark:text-slate-200">Click to upload Excel</p>
                                    <p class="text-xs text-slate-500" id="transferFileName">Required column: MSISDN</p>
                                </div>
                                <p class="text-xs text-green-600 mt-2 hidden" id="transferFileStatus">Ready to transfer <span id="transferFileCount">0</span> numbers.</p>
                            </div>

                            <div class="mt-6">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Destination Company</label>
                                <select name="target_company_id" required class="w-full p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none transition-all">
                                    <option value="" disabled selected>-- Select Target Company --</option>
                                    <?php foreach($compArr as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700">
                            <button type="button" onclick="closeModal('transferModal')" class="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-500/20 transition-all active:scale-95">Confirm Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // --- 1. DYNAMIC UNIT CONVERSION (BASIS 1000) ---
        function updateDataUnits() {
            const unit = document.getElementById('unitSelector').value;
            const elements = document.querySelectorAll('.dynamic-data');
            
            elements.forEach(el => {
                const rawBytes = parseFloat(el.getAttribute('data-bytes'));
                if(isNaN(rawBytes)) return;

                const baseMB = rawBytes / 1048576; 

                let val = 0;
                if(unit === 'KB') val = baseMB * 1000;
                else if(unit === 'MB') val = baseMB;
                else if(unit === 'GB') val = baseMB / 1000;

                el.innerText = val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + unit;
            });
        }

        // --- 2. COLUMN LOGIC ---
        const CONFIG_KEY = 'simTableConfig_V10'; 
        const defaultConfig = [
            { id: 'checkbox', name: '', width: 50, frozen: true, visible: true },
            { id: 'tags', name: 'Tags', width: 120, frozen: true, visible: true },
            { id: 'msisdn', name: 'MSISDN', width: 150, frozen: true, visible: true },
            { id: 'customer', name: 'Customer', width: 200, frozen: true, visible: true },
            { id: 'level', name: 'Level', width: 80, frozen: false, visible: true },
            { id: 'batch', name: 'Batch', width: 120, frozen: false, visible: true },
            { id: 'card_type', name: 'Card Type', width: 120, frozen: false, visible: true },
            { id: 'expired_date', name: 'Expired Date', width: 130, frozen: false, visible: true },
            { id: 'invoice', name: 'Invoice No', width: 120, frozen: false, visible: true },
            { id: 'project', name: 'Project', width: 140, frozen: false, visible: true },
            { id: 'imsi', name: 'IMSI', width: 140, frozen: false, visible: true },
            { id: 'iccid', name: 'ICCID', width: 160, frozen: false, visible: true },
            { id: 'sn', name: 'SN', width: 100, frozen: false, visible: true },
            { id: 'package', name: 'Package', width: 180, frozen: false, visible: true }, 
            { id: 'usage', name: 'Usage', width: 200, frozen: false, visible: true },
            { id: 'action', name: 'Action', width: 80, frozen: false, visible: true }
        ];
        let colConfig = [];

        function initColumns() {
            const saved = localStorage.getItem(CONFIG_KEY);
            colConfig = saved ? JSON.parse(saved) : JSON.parse(JSON.stringify(defaultConfig));
            renderTableColumns();
            renderColumnControls();
        }
        function resetColumnConfig() { colConfig = JSON.parse(JSON.stringify(defaultConfig)); saveAndApply(); }
        function saveAndApply() { localStorage.setItem(CONFIG_KEY, JSON.stringify(colConfig)); renderTableColumns(); renderColumnControls(); }

        function renderTableColumns() {
            const headerRow = document.getElementById('tableHeaderRow');
            const rows = document.querySelectorAll('#tableBody tr');
            const frozenCols = colConfig.filter(c => c.frozen);
            const unfrozenCols = colConfig.filter(c => !c.frozen);
            const displayOrder = [...frozenCols, ...unfrozenCols];
            let currentLeft = 0;
            const stickyOffsets = {};
            displayOrder.forEach(col => {
                if(col.visible && col.frozen) { stickyOffsets[col.id] = currentLeft; currentLeft += col.width; }
            });
            const lastFrozenId = frozenCols.length > 0 ? frozenCols[frozenCols.length - 1].id : null;
            const applyStyles = (cell, col, isLastFrozen) => {
                cell.style.display = col.visible ? 'table-cell' : 'none';
                if (col.visible && col.frozen) {
                    cell.style.position = 'sticky'; cell.style.left = stickyOffsets[col.id] + 'px';
                    cell.style.width = col.width + 'px'; cell.style.minWidth = col.width + 'px';
                    cell.classList.add('sticky-col');
                    if (isLastFrozen) cell.classList.add('sticky-edge-shadow'); else cell.classList.remove('sticky-edge-shadow');
                } else {
                    cell.style.position = ''; cell.style.left = ''; cell.style.width = ''; cell.style.minWidth = '';
                    cell.classList.remove('sticky-col', 'sticky-edge-shadow');
                }
            };
            displayOrder.forEach(col => { const th = headerRow.querySelector(`th[data-col="${col.id}"]`); if(th) { headerRow.appendChild(th); applyStyles(th, col, col.id === lastFrozenId); } });
            rows.forEach(row => { if(row.children.length > 1) { displayOrder.forEach(col => { const td = row.querySelector(`td[data-col="${col.id}"]`); if(td) { row.appendChild(td); applyStyles(td, col, col.id === lastFrozenId); } }); } });
        }

        function renderColumnControls() {
            const container = document.getElementById('colListContainer'); container.innerHTML = '';
            colConfig.forEach((col, index) => {
                if(col.id === 'checkbox') return;
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-800/50 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors group';
                div.innerHTML = `<div class="flex items-center gap-2 overflow-hidden"><div class="flex flex-col gap-0.5 text-slate-400 opacity-50 group-hover:opacity-100 cursor-pointer"><i onclick="moveCol(${index}, -1)" class="ph-bold ph-caret-up hover:text-primary text-[10px]"></i><i onclick="moveCol(${index}, 1)" class="ph-bold ph-caret-down hover:text-primary text-[10px]"></i></div><label class="flex items-center gap-2 cursor-pointer truncate select-none"><input type="checkbox" onchange="toggleVis('${col.id}')" ${col.visible ? 'checked' : ''} class="rounded border-slate-300 text-primary focus:ring-primary w-3.5 h-3.5"><span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate">${col.name}</span></label></div><button onclick="toggleFreeze('${col.id}')" class="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors ${col.frozen ? 'text-primary bg-indigo-50 dark:bg-indigo-900/30' : 'text-slate-400'}" title="${col.frozen ? 'Unfreeze' : 'Freeze'}"><i class="ph-fill ${col.frozen ? 'ph-push-pin' : 'ph-push-pin-slash'} text-sm"></i></button>`;
                container.appendChild(div);
            });
        }
        window.toggleVis = (id) => { const col = colConfig.find(c => c.id === id); col.visible = !col.visible; saveAndApply(); };
        window.toggleFreeze = (id) => { const col = colConfig.find(c => c.id === id); col.frozen = !col.frozen; saveAndApply(); };
        window.moveCol = (index, direction) => {
            if (direction === -1 && index > 0) { if(colConfig[index-1].id === 'checkbox') return; [colConfig[index], colConfig[index - 1]] = [colConfig[index - 1], colConfig[index]]; }
            else if (direction === 1 && index < colConfig.length - 1) { [colConfig[index], colConfig[index + 1]] = [colConfig[index + 1], colConfig[index]]; }
            saveAndApply();
        };
        document.addEventListener('DOMContentLoaded', initColumns);

        // --- TAG INPUT LOGIC ---
        let currentTags = [];
        const tagContainer = document.querySelector('.tag-input-container');
        const tagInputText = document.getElementById('tagInputText');

        function initTagInput(initialTags = "") {
            currentTags = initialTags ? initialTags.split(',').map(t => t.trim()).filter(t => t) : [];
            renderTags();
        }

        function renderTags() {
            document.querySelectorAll('.tag-chip').forEach(el => el.remove());
            currentTags.forEach(tag => {
                const chip = document.createElement('div');
                chip.className = 'tag-chip';
                chip.innerHTML = `${tag} <i class="ph ph-x cursor-pointer hover:text-red-500" onclick="removeTag('${tag}')"></i>`;
                tagContainer.insertBefore(chip, tagInputText);
            });
        }

        function removeTag(tag) {
            currentTags = currentTags.filter(t => t !== tag);
            renderTags();
        }

        tagInputText.addEventListener('keydown', (e) => {
            if(e.key === 'Enter') {
                e.preventDefault();
                const val = tagInputText.value.trim().toUpperCase();
                if(val && !currentTags.includes(val)) {
                    currentTags.push(val);
                    renderTags();
                    tagInputText.value = '';
                }
            } else if (e.key === 'Backspace' && tagInputText.value === '' && currentTags.length > 0) {
                currentTags.pop();
                renderTags();
            }
        });

        function submitTagForm() {
            document.getElementById('tags_final').value = currentTags.join(',');
            document.getElementById('tagForm').submit();
        }

        function handleSearchEnter(e) {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btnFilter').click(); }
        }

        // --- MODALS ---
        function animateModal(modalId, show) {
            const modal = document.getElementById(modalId);
            const backdrop = modal.querySelector('div[id$="Backdrop"]');
            const panel = modal.querySelector('div[id$="Panel"]');
            if(show) {
                modal.classList.remove('hidden');
                setTimeout(() => { backdrop.classList.remove('opacity-0'); panel.classList.remove('opacity-0', 'scale-95'); panel.classList.add('opacity-100', 'scale-100'); }, 10);
            } else {
                backdrop.classList.add('opacity-0'); panel.classList.remove('opacity-100', 'scale-100'); panel.classList.add('opacity-0', 'scale-95');
                setTimeout(() => modal.classList.add('hidden'), 300);
            }
        }
        function closeModal(id) { animateModal(id, false); }

        function openTagModal(id, tags) { 
            document.getElementById('tagModalTitle').innerText = "Edit Tags";
            document.getElementById('modal_tag_ids').value = id;
            initTagInput(tags);
            animateModal('tagModal', true); 
        }
        function openBulkTagModal() {
            const checked = document.querySelectorAll('.sim-check:checked');
            if(checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('tagModalTitle').innerText = "Bulk Set Tags (" + checked.length + ")";
            document.getElementById('modal_tag_ids').value = ids;
            initTagInput(""); 
            animateModal('tagModal', true);
        }

        function openSingleProjectModal(id, currentProj) {
            document.getElementById('projectModalTitle').innerText = "Edit Project";
            document.getElementById('modal_project_ids').value = id;
            document.getElementById('modal_project_input').value = currentProj;
            animateModal('projectModal', true);
        }
        function openBulkProjectModal() {
            const checked = document.querySelectorAll('.sim-check:checked');
            if(checked.length === 0) return;
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            document.getElementById('projectModalTitle').innerText = "Bulk Set Project (" + checked.length + ")";
            document.getElementById('modal_project_ids').value = ids;
            document.getElementById('modal_project_input').value = "";
            animateModal('projectModal', true);
        }

        // --- NEW: TRANSFER LOGIC ---
        function openTransferModal() {
            const checked = document.querySelectorAll('.sim-check:checked');
            if(checked.length === 0) {
                setTransferTab('upload');
            } else {
                const ids = Array.from(checked).map(cb => cb.value).join(',');
                document.getElementById('transfer_sim_ids').value = ids;
                document.getElementById('transferCountDisplay').innerText = checked.length;
                setTransferTab('selection');
            }
            animateModal('transferModal', true);
        }

        function setTransferTab(tab) {
            const tabSel = document.getElementById('tab-selection');
            const tabUpl = document.getElementById('tab-upload');
            const contentSel = document.getElementById('content-selection');
            const contentUpl = document.getElementById('content-upload');
            const inputType = document.getElementById('transfer_type');

            if (tab === 'selection') {
                tabSel.classList.replace('text-slate-500', 'bg-white');
                tabSel.classList.replace('hover:bg-white/50', 'shadow-sm');
                tabSel.classList.add('text-primary');
                
                tabUpl.classList.replace('bg-white', 'text-slate-500');
                tabUpl.classList.replace('shadow-sm', 'hover:bg-white/50');
                tabUpl.classList.remove('text-primary');

                contentSel.classList.remove('hidden');
                contentUpl.classList.add('hidden');
                inputType.value = 'selection';
            } else {
                tabUpl.classList.replace('text-slate-500', 'bg-white');
                tabUpl.classList.replace('hover:bg-white/50', 'shadow-sm');
                tabUpl.classList.add('text-primary');

                tabSel.classList.replace('bg-white', 'text-slate-500');
                tabSel.classList.replace('shadow-sm', 'hover:bg-white/50');
                tabSel.classList.remove('text-primary');

                contentSel.classList.add('hidden');
                contentUpl.classList.remove('hidden');
                inputType.value = 'upload';
            }
        }

        function handleTransferFile(input) {
            const file = input.files[0];
            if(!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, {type: 'array'});
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                let jsonData = XLSX.utils.sheet_to_json(firstSheet);
                
                let msisdns = [];
                jsonData.forEach(row => {
                    const keys = Object.keys(row);
                    const msisdnKey = keys.find(k => k.toUpperCase().trim() === 'MSISDN');
                    if(msisdnKey && row[msisdnKey]) {
                        msisdns.push(row[msisdnKey]);
                    }
                });

                if(msisdns.length > 0) {
                    document.getElementById('transferFileName').innerText = file.name;
                    document.getElementById('transferFileStatus').classList.remove('hidden');
                    document.getElementById('transferFileCount').innerText = msisdns.length;
                    document.getElementById('transfer_msisdn_json').value = JSON.stringify(msisdns);
                } else {
                    alert("No valid MSISDN column found.");
                    input.value = "";
                }
            };
            reader.readAsArrayBuffer(file);
        }

        // --- BULK UI ---
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.sim-check');
        const bulkBar = document.getElementById('bulkActionBar');
        
        function updateBulkUI() {
            const checked = document.querySelectorAll('.sim-check:checked');
            document.getElementById('selectedCount').innerText = checked.length;
            if(checked.length > 0) { bulkBar.classList.remove('hidden'); bulkBar.classList.add('flex'); }
            else { bulkBar.classList.add('hidden'); bulkBar.classList.remove('flex'); }
        }
        if(selectAll) selectAll.addEventListener('change', function() { checkboxes.forEach(cb => cb.checked = selectAll.checked); updateBulkUI(); });
        checkboxes.forEach(cb => cb.addEventListener('change', updateBulkUI));

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.remove('translate-x-full', 'opacity-0');
            setTimeout(() => { document.getElementById('toast').classList.add('translate-x-full', 'opacity-0'); }, 3000);
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('msg') === 'tags_updated') showToast('Tags updated successfully.');
        if(urlParams.get('msg') === 'project_updated') showToast('Project updated successfully.');
        if(urlParams.get('msg') === 'transfer_success') showToast('SIM cards transferred successfully.');

        function changePageSize(size) {
            const url = new URL(window.location.href); url.searchParams.set('size', size); url.searchParams.set('page', 1); window.location.href = url.toString();
        }
    </script>
</body>
</html>