<?php 
include 'config.php';
checkLogin();

enforcePermission('sim_upload');

// --- Helper untuk Konversi Tanggal Excel ke MySQL Format ---
function excelDateToMySQL($excelDate) {
    if (empty($excelDate)) return null;
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $excelDate)) {
        return $excelDate;
    }
    if (is_numeric($excelDate)) {
        $unix_date = ($excelDate - 25569) * 86400;
        return gmdate("Y-m-d", $unix_date);
    }
    $time = strtotime($excelDate);
    if ($time !== false) {
        return date('Y-m-d', $time);
    }
    return null; 
}

// --- PHP BACKEND HANDLER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    ini_set('display_errors', 0); 
    ini_set('memory_limit', '512M');
    set_time_limit(300);
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON Input']);
        exit;
    }

    // --- HANDLER 1: SIM REGISTER / UPLOAD ---
    if (isset($input['action']) && $input['action'] == 'upload_sim_batch') {
        $company_id = $input['company_id'] ?? null;
        $data = $input['data'] ?? [];
        
        if (!$company_id || empty($data)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing Company ID or Data']);
            exit;
        }

        $inserted = 0;
        $updated = 0; 
        $fail = 0;
        $errors = [];

        try {
            // Persiapkan Statement untuk Cek, Update, dan Insert
            $stmt_check = $conn->prepare("SELECT imsi, iccid, sn, invoice_number, total_flow, custom_project, batch, card_type, expired_date FROM sims WHERE msisdn = ? LIMIT 1");
            
            $stmt_update = $conn->prepare("UPDATE sims SET company_id=?, imsi=?, iccid=?, sn=?, invoice_number=?, total_flow=?, custom_project=?, batch=?, card_type=?, expired_date=? WHERE msisdn=?");
            
            $stmt_insert = $conn->prepare("INSERT INTO sims (company_id, msisdn, imsi, iccid, sn, invoice_number, total_flow, custom_project, batch, card_type, expired_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            if (!$stmt_check || !$stmt_update || !$stmt_insert) {
                throw new Exception("Database Prepare Error: " . $conn->error);
            }

            foreach ($data as $row) {
                $msisdn  = !empty($row['MSISDN']) ? (string)$row['MSISDN'] : null;
                if (!$msisdn) {
                    $fail++;
                    if (count($errors) < 5) $errors[] = "Row skipped: MSISDN is missing.";
                    continue;
                }

                $card_type   = !empty($row['CARD TYPE']) ? (string)$row['CARD TYPE'] : null;
                $imsi        = !empty($row['IMSI']) ? (string)$row['IMSI'] : null;  
                $iccid       = !empty($row['ICCID']) ? (string)$row['ICCID'] : null; 
                $sn          = !empty($row['SN']) ? (string)$row['SN'] : null;
                $invoice     = !empty($row['INVOICE NO']) ? (string)$row['INVOICE NO'] : null;
                $project     = !empty($row['PROJECT']) ? (string)$row['PROJECT'] : null;
                $batch       = !empty($row['BATCH']) ? (string)$row['BATCH'] : null; 
                $expired_raw = !empty($row['EXPIRED DATE']) ? $row['EXPIRED DATE'] : null;
                
                $expired_date = excelDateToMySQL($expired_raw);
                
                $quotaRaw = preg_replace('/[^0-9.]/', '', $row['DATAPACKAGE'] ?? '');
                $totalFlowBytes = null;
                if ($quotaRaw !== '') {
                    $quotaMB = floatval($quotaRaw);
                    $totalFlowBytes = $quotaMB * 1024 * 1024; 
                }

                // Cek DB apakah MSISDN sudah ada
                $stmt_check->bind_param("s", $msisdn);
                $stmt_check->execute();
                $res = $stmt_check->get_result();

                if ($res->num_rows > 0) {
                    // DATA ADA -> LAKUKAN UPDATE (SMART MERGE)
                    // Jika data di Excel kosong, gunakan data lama yang ada di DB. Jika di Excel diisi, timpa data lama.
                    $existing = $res->fetch_assoc();
                    
                    $upd_company   = $company_id;
                    $upd_imsi      = $imsi !== null ? $imsi : $existing['imsi'];
                    $upd_iccid     = $iccid !== null ? $iccid : $existing['iccid'];
                    $upd_sn        = $sn !== null ? $sn : $existing['sn'];
                    $upd_invoice   = $invoice !== null ? $invoice : $existing['invoice_number'];
                    $upd_project   = $project !== null ? $project : $existing['custom_project'];
                    $upd_batch     = $batch !== null ? $batch : $existing['batch'];
                    $upd_card_type = $card_type !== null ? $card_type : $existing['card_type'];
                    $upd_expired   = $expired_date !== null ? $expired_date : $existing['expired_date'];
                    $upd_flow      = $totalFlowBytes !== null ? $totalFlowBytes : $existing['total_flow'];

                    $stmt_update->bind_param("isssssdssss", $upd_company, $upd_imsi, $upd_iccid, $upd_sn, $upd_invoice, $upd_flow, $upd_project, $upd_batch, $upd_card_type, $upd_expired, $msisdn);
                    
                    if ($stmt_update->execute()) {
                        $updated++;
                    } else {
                        $fail++;
                        if (count($errors) < 5) $errors[] = "Update failed ($msisdn): " . $stmt_update->error;
                    }

                } else {
                    // DATA TIDAK ADA -> LAKUKAN INSERT BARU
                    if (!$card_type) {
                        $fail++;
                        if (count($errors) < 5) $errors[] = "Insert skipped ($msisdn): CARD TYPE is strictly required for new SIM.";
                        continue;
                    }

                    $ins_flow = $totalFlowBytes !== null ? $totalFlowBytes : 0;
                    $stmt_insert->bind_param("isssssdssss", $company_id, $msisdn, $imsi, $iccid, $sn, $invoice, $ins_flow, $project, $batch, $card_type, $expired_date);
                    
                    if ($stmt_insert->execute()) {
                        $inserted++;
                    } else {
                        $fail++;
                        if (count($errors) < 5) $errors[] = "Insert failed ($msisdn): " . $stmt_insert->error;
                    }
                }
            }
            
            echo json_encode([
                'status' => 'success', 
                'processed' => count($data), 
                'inserted' => $inserted, 
                'updated' => $updated,
                'fail' => $fail,
                'debug_errors' => $errors
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Critical Error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // --- HANDLER 2: USAGE UPDATE ---
    if (isset($input['action']) && $input['action'] == 'update_usage_batch') {
        $data = $input['data'] ?? [];
        $success = 0;
        $fail = 0;
        
        try {
            $stmt = $conn->prepare("UPDATE sims SET used_flow = ? WHERE msisdn = ?");
            if (!$stmt) throw new Exception("Database Prepare Error: " . $conn->error);

            foreach ($data as $row) {
                $msisdn = !empty($row['MSISDN']) ? (string)$row['MSISDN'] : null;
                $usageRaw = preg_replace('/[^0-9.]/', '', $row['USAGE'] ?? '0');
                $usageMB = floatval($usageRaw);
                $usageBytes = $usageMB * 1024 * 1024; 

                if ($msisdn) {
                    $stmt->bind_param("ds", $usageBytes, $msisdn);
                    if ($stmt->execute() && $stmt->affected_rows >= 0) {
                        $success++;
                    } else {
                        $fail++;
                    }
                } else {
                    $fail++;
                }
            }
            echo json_encode(['status' => 'success', 'processed' => count($data), 'success' => $success, 'fail' => $fail]);
        
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get Companies for Dropdown
$compArr = []; 
$allowed_comps = getClientIdsForUser($_SESSION['user_id']);
$compWhere = is_array($allowed_comps) ? "WHERE id IN (" . implode(',', $allowed_comps) . ")" : ($allowed_comps === 'NONE' ? "WHERE 1=0" : "");
$cQ = $conn->query("SELECT id, company_name FROM companies $compWhere ORDER BY company_name");
while($r = $cQ->fetch_assoc()) $compArr[] = $r;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIM Upload Center</title>
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
                    animation: { 'slide-up': 'slideUp 0.4s ease-out forwards' },
                    keyframes: { slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } } }
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
                
                <div class="mb-8 animate-slide-up">
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-primary">
                            <i class="ph ph-upload-simple text-2xl"></i>
                        </div>
                        Batch Upload Center
                    </h2>
                    <p class="text-sm text-slate-500 mt-1 ml-12">Import SIM data or update usage efficiently using Excel files.</p>
                </div>

                <div class="mb-6 border-b border-slate-200 dark:border-slate-700 animate-slide-up" style="animation-delay: 0.1s;">
                    <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="uploadTabs">
                        <li class="mr-2">
                            <button onclick="switchTab('sim-upload')" id="tab-sim-upload" class="inline-flex items-center gap-2 p-4 border-b-2 border-primary text-primary active dark:text-indigo-400 dark:border-indigo-400 transition-colors">
                                <i class="ph ph-sim-card text-lg"></i> Register New / Update
                            </button>
                        </li>
                        <li class="mr-2">
                            <button onclick="switchTab('usage-upload')" id="tab-usage-upload" class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent hover:text-slate-600 hover:border-slate-300 dark:hover:text-slate-300 transition-colors">
                                <i class="ph ph-chart-bar text-lg"></i> Update Usage Data
                            </button>
                        </li>
                    </ul>
                </div>

                <div id="view-sim-upload" class="animate-slide-up" style="animation-delay: 0.2s;">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            
                            <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                                <label class="block text-sm font-bold text-slate-700 dark:text-white mb-2">1. Select Target Company <span class="text-red-500">*</span></label>
                                <select id="companySelect" class="w-full p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none transition-all">
                                    <option value="" disabled selected>-- Select Company --</option>
                                    <?php foreach($compArr as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="bg-white dark:bg-darkcard p-8 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 relative group">
                                <div id="dropzoneSim" class="text-center">
                                    <input type="file" id="fileSim" accept=".xlsx, .xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" onchange="handleFileSelect(this, 'sim')">
                                    <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-10 transition-all group-hover:border-primary group-hover:bg-indigo-50/30">
                                        <div class="w-16 h-16 bg-indigo-50 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4 text-primary">
                                            <i class="ph ph-microsoft-excel-logo text-3xl"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Click to Upload Excel</h3>
                                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">.xlsx or .xls</p>
                                        <p class="text-xs text-slate-400 mt-2" id="fileNameSim"></p>
                                    </div>
                                </div>

                                <div id="previewSim" class="hidden mt-6">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-bold text-slate-700 dark:text-white">Data Preview (First 5 rows)</h4>
                                        <span class="text-xs font-bold bg-green-100 text-green-700 px-2 py-1 rounded">Valid Format</span>
                                    </div>
                                    <div class="overflow-x-auto border rounded-lg dark:border-slate-700">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-500 font-bold uppercase">
                                                <tr id="previewHeaderSim"></tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700" id="previewBodySim"></tbody>
                                        </table>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2 text-right">Total rows found: <strong id="rowCountSim">0</strong></p>
                                </div>
                            </div>

                            <div id="progressAreaSim" class="hidden bg-white dark:bg-darkcard p-6 rounded-2xl shadow-lg border border-indigo-100 dark:border-slate-700">
                                <div class="flex justify-between text-sm font-bold mb-2">
                                    <span class="text-primary" id="statusTextSim">Uploading...</span>
                                    <span class="text-slate-600 dark:text-white" id="percentSim">0%</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2.5 mb-4 overflow-hidden">
                                    <div id="barSim" class="bg-primary h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-center">
                                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100 text-emerald-700 font-bold">
                                        <span id="successCountSim" class="text-xl block">0</span> New Inserted
                                    </div>
                                    <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 text-amber-700 font-bold">
                                        <span id="duplicateCountSim" class="text-xl block">0</span> Updated (Existing)
                                    </div>
                                    <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 text-red-700 font-bold">
                                        <span id="failCountSim" class="text-xl block">0</span> Failed
                                    </div>
                                </div>
                                <div id="errorLogSim" class="hidden mt-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-xs text-red-600 font-mono border border-red-100 dark:border-red-800">
                                    <strong>Last Errors:</strong> <span id="errorMsgSim"></span>
                                </div>
                            </div>

                            <button id="btnUploadSim" disabled onclick="startUploadSim()" class="w-full py-4 bg-primary hover:bg-indigo-600 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg transition-all">
                                Start Process
                            </button>
                        </div>

                        <div class="lg:col-span-1">
                            <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-2xl p-6 text-white shadow-xl">
                                <h3 class="font-bold text-lg mb-4">Required Columns</h3>
                                <ul class="space-y-3 text-sm text-indigo-100">
                                    <li><span class="font-bold text-white">• MSISDN</span> (Required)</li>
                                    <li>• IMSI (Optional)</li>
                                    <li>• ICCID (Optional)</li>
                                    <li>• SN (Optional)</li>
                                    <li>• INVOICE NO (Text)</li>
                                    <li>• DATAPACKAGE (Num in MB)</li>
                                    <li>• PROJECT (Optional)</li>
                                    <li>• BATCH (Optional)</li>
                                    <li>• EXPIRED DATE (Optional)</li>
                                    <li><span class="font-bold text-white">• CARD TYPE</span> (Required)</li>
                                </ul>
                                <button onclick="downloadTemplate('sim')" class="mt-6 w-full py-2 bg-white text-indigo-600 font-bold rounded-lg text-xs hover:bg-indigo-50 transition-colors shadow-sm">Download Template</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="view-usage-upload" class="hidden animate-slide-up">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white dark:bg-darkcard p-8 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 text-center relative group">
                                <div id="dropzoneUsage">
                                    <input type="file" id="fileUsage" accept=".xlsx, .xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" onchange="handleFileSelect(this, 'usage')">
                                    <div class="border-2 border-dashed border-emerald-300 rounded-xl p-10 transition-all group-hover:bg-emerald-50/30">
                                        <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
                                            <i class="ph ph-chart-line-up text-3xl"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Upload Usage Excel</h3>
                                        <span id="fileNameUsage" class="text-xs text-slate-500 block mt-2"></span>
                                    </div>
                                </div>
                                
                                <div id="previewUsage" class="hidden mt-6">
                                    <h4 class="text-sm font-bold text-left mb-2 dark:text-white">Preview Data</h4>
                                    <div class="overflow-x-auto border rounded-lg dark:border-slate-700">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-slate-50 dark:bg-slate-800 text-slate-500 font-bold"><tr id="previewHeaderUsage"></tr></thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700" id="previewBodyUsage"></tbody>
                                        </table>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2 text-right">Total rows: <strong id="rowCountUsage">0</strong></p>
                                </div>
                            </div>

                            <div id="progressAreaUsage" class="hidden bg-white dark:bg-darkcard p-6 rounded-2xl shadow-lg border border-emerald-100 dark:border-slate-700">
                                <div class="flex justify-between text-sm font-bold mb-2">
                                    <span class="text-emerald-600">Processing...</span>
                                    <span class="text-slate-600 dark:text-white" id="percentUsage">0%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2.5 mb-4"><div id="barUsage" class="bg-emerald-500 h-2.5 rounded-full" style="width: 0%"></div></div>
                                <div class="text-center font-bold text-emerald-600">
                                    <span id="successCountUsage">0</span> Updated
                                </div>
                            </div>

                            <button id="btnUploadUsage" disabled onclick="startUploadUsage()" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold rounded-xl shadow-lg transition-all">Start Update</button>
                        </div>

                        <div class="lg:col-span-1">
                            <div class="bg-gradient-to-br from-emerald-600 to-emerald-800 rounded-2xl p-6 text-white shadow-xl">
                                <h3 class="font-bold text-lg mb-4">Required Columns</h3>
                                <ul class="space-y-3 text-sm text-emerald-100">
                                    <li><span class="font-bold text-white">• MSISDN</span> (Required)</li>
                                    <li>• USAGE (MB)</li>
                                </ul>
                                <button onclick="downloadTemplate('usage')" class="mt-6 w-full py-2 bg-white text-emerald-700 font-bold rounded-lg text-xs hover:bg-emerald-50 transition-colors shadow-sm">Download Template</button>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // --- 1. TAB SWITCHING ---
        function switchTab(tab) {
            document.querySelectorAll('[id^="view-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-"]').forEach(el => {
                el.classList.remove('border-primary', 'text-primary', 'dark:text-indigo-400', 'dark:border-indigo-400');
                el.classList.add('border-transparent', 'text-slate-500');
            });
            document.getElementById('view-' + tab).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-' + tab);
            activeBtn.classList.remove('border-transparent', 'text-slate-500');
            activeBtn.classList.add('border-primary', 'text-primary', 'dark:text-indigo-400', 'dark:border-indigo-400');
        }

        // --- 2. GLOBAL DATA STORAGE ---
        let simData = [];
        let usageData = [];

        // --- 3. HELPER: NORMALIZE KEYS ---
        function normalizeKeys(obj) {
            const newObj = {};
            Object.keys(obj).forEach(key => {
                newObj[key.trim().toUpperCase()] = obj[key];
            });
            return newObj;
        }

        // --- 4. HANDLE FILE SELECT & PREVIEW ---
        function handleFileSelect(input, type) {
            const file = input.files[0];
            if (!file) return;

            document.getElementById(`fileName${capitalize(type)}`).innerText = file.name;
            document.getElementById(`preview${capitalize(type)}`).classList.add('hidden');
            document.getElementById(`btnUpload${capitalize(type)}`).disabled = true;

            const reader = new FileReader();
            reader.onload = function(e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                let jsonData = XLSX.utils.sheet_to_json(firstSheet, { raw: false, dateNF: 'yyyy-mm-dd' });

                jsonData = jsonData.map(normalizeKeys);

                if (jsonData.length === 0) {
                    alert("File is empty or format invalid.");
                    return;
                }

                let missing = [];
                if (type === 'sim') {
                    const req = ['MSISDN', 'CARD TYPE'];
                    const keys = Object.keys(jsonData[0]);
                    missing = req.filter(col => !keys.includes(col));
                    simData = jsonData;
                } else {
                    const req = ['MSISDN', 'USAGE'];
                    const keys = Object.keys(jsonData[0]);
                    missing = req.filter(col => !keys.includes(col));
                    usageData = jsonData;
                }

                if (missing.length > 0) {
                    alert("Missing mandatory columns: " + missing.join(", "));
                    input.value = ""; 
                    return;
                }

                renderPreview(jsonData.slice(0, 5), type);
                
                document.getElementById(`rowCount${capitalize(type)}`).innerText = jsonData.length;
                document.getElementById(`preview${capitalize(type)}`).classList.remove('hidden');
                document.getElementById(`btnUpload${capitalize(type)}`).disabled = false;
            };
            reader.readAsArrayBuffer(file);
        }

        function renderPreview(data, type) {
            const thead = document.getElementById(`previewHeader${capitalize(type)}`);
            const tbody = document.getElementById(`previewBody${capitalize(type)}`);
            thead.innerHTML = "";
            tbody.innerHTML = "";

            if (data.length > 0) {
                Object.keys(data[0]).forEach(key => {
                    thead.innerHTML += `<th class="px-4 py-2 bg-slate-50 dark:bg-slate-800">${key}</th>`;
                });
                data.forEach(row => {
                    let tr = "<tr class='hover:bg-slate-50 dark:hover:bg-slate-800/50'>";
                    Object.values(row).forEach(val => {
                        tr += `<td class="px-4 py-2 border-b dark:border-slate-700 text-slate-600 dark:text-slate-300 truncate max-w-[150px]">${val}</td>`;
                    });
                    tr += "</tr>";
                    tbody.innerHTML += tr;
                });
            }
        }

        function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

        // --- 5. PROCESS UPLOAD SIM ---
        async function startUploadSim() {
            const companyId = document.getElementById('companySelect').value;
            if (!companyId) { alert("Please select a target company first!"); return; }
            if (simData.length === 0) { alert("No data to upload."); return; }

            const btn = document.getElementById('btnUploadSim');
            btn.disabled = true;
            btn.innerHTML = `<i class="ph ph-spinner animate-spin"></i> Processing...`;
            
            document.getElementById('progressAreaSim').classList.remove('hidden');
            
            let totalInserted = 0;
            let totalUpdated = 0;
            let totalFail = 0;
            let processed = 0;
            const batchSize = 50; 

            for (let i = 0; i < simData.length; i += batchSize) {
                const batch = simData.slice(i, i + batchSize);
                
                try {
                    const res = await fetch('sim-upload.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            action: 'upload_sim_batch', 
                            company_id: companyId, 
                            data: batch 
                        })
                    });
                    
                    const result = await res.json();
                    
                    if(result.status === 'error') {
                        console.error("Server Error:", result.message);
                        totalFail += batch.length;
                        document.getElementById('errorLogSim').classList.remove('hidden');
                        document.getElementById('errorMsgSim').innerText = result.message;
                    } else {
                        totalInserted += result.inserted || 0;
                        totalUpdated += result.updated || 0;
                        totalFail += result.fail || 0;
                        
                        if(result.debug_errors && result.debug_errors.length > 0) {
                             document.getElementById('errorLogSim').classList.remove('hidden');
                             document.getElementById('errorMsgSim').innerText = result.debug_errors[0];
                        }
                    }
                } catch (err) {
                    console.error("Network/JSON Error:", err);
                    totalFail += batch.length;
                }

                processed += batch.length;
                const percent = Math.round((processed / simData.length) * 100);
                
                document.getElementById('barSim').style.width = percent + '%';
                document.getElementById('percentSim').innerText = percent + '%';
                document.getElementById('successCountSim').innerText = totalInserted;
                document.getElementById('duplicateCountSim').innerText = totalUpdated;
                document.getElementById('failCountSim').innerText = totalFail;
            }

            document.getElementById('statusTextSim').innerText = "Process Completed!";
            btn.innerHTML = "Upload Complete";
            btn.classList.add('bg-green-600', 'hover:bg-green-700');
        }

        // --- 6. PROCESS UPLOAD USAGE ---
        async function startUploadUsage() {
            if (usageData.length === 0) return;
            const btn = document.getElementById('btnUploadUsage');
            btn.disabled = true;
            btn.innerHTML = `<i class="ph ph-spinner animate-spin"></i> Processing...`;
            document.getElementById('progressAreaUsage').classList.remove('hidden');

            let totalSuccess = 0;
            let processed = 0;
            const batchSize = 100;

            for (let i = 0; i < usageData.length; i += batchSize) {
                const batch = usageData.slice(i, i + batchSize);
                try {
                    const res = await fetch('sim-upload.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_usage_batch', data: batch })
                    });
                    const result = await res.json();
                    if(result.status === 'success') totalSuccess += result.success;
                } catch (err) { console.error(err); }

                processed += batch.length;
                const percent = Math.round((processed / usageData.length) * 100);
                document.getElementById('barUsage').style.width = percent + '%';
                document.getElementById('percentUsage').innerText = percent + '%';
                document.getElementById('successCountUsage').innerText = totalSuccess;
            }
            btn.innerHTML = "Update Complete";
            btn.classList.add('bg-green-600');
        }

        function downloadTemplate(type) {
            const wb = XLSX.utils.book_new();
            let data = [];
            if (type === 'sim') data = [[ "MSISDN", "IMSI", "ICCID", "SN", "INVOICE NO", "DATAPACKAGE", "PROJECT", "BATCH", "EXPIRED DATE", "CARD TYPE" ]];
            else data = [[ "MSISDN", "USAGE" ]];
            const ws = XLSX.utils.aoa_to_sheet(data);
            XLSX.utils.book_append_sheet(wb, ws, "Template");
            XLSX.writeFile(wb, `${type}_template.xlsx`);
        }
    </script>
</body>
</html>