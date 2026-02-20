<?php 
include 'config.php';
checkLogin();
enforcePermission('sim_list');

// --- 1. PREPARATION & HELPER ---
$user_id = $_SESSION['user_id'];
$iccid_req = $conn->real_escape_string($_GET['iccid'] ?? '');

if (empty($iccid_req)) {
    header("Location: sim-list.php");
    exit();
}

function formatBytesMB($bytes) { 
    if ($bytes <= 0) return '0.00 MB';
    return number_format($bytes / 1048576, 2) . ' MB';
}

// --- 2. DATA ACCESS CONTROL ---
$allowed_comps = getClientIdsForUser($user_id);
$company_condition = "";
if ($allowed_comps === 'NONE') {
    $company_condition = " AND 1=0 "; 
} elseif (is_array($allowed_comps)) {
    $ids_str = implode(',', $allowed_comps);
    $company_condition = " AND sims.company_id IN ($ids_str) ";
} 

// --- 3. FETCH SIM DATA ---
$sql = "SELECT sims.*, companies.company_name, companies.level 
        FROM sims 
        LEFT JOIN companies ON sims.company_id = companies.id 
        WHERE sims.iccid = '$iccid_req' $company_condition LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo "<script>alert('SIM Card not found or Access Denied!'); window.location='sim-list.php';</script>";
    exit();
}

$sim = $result->fetch_assoc();

// Cek apakah ini Telkomsel Halo
$isHalo = false;
$cardType = strtolower($sim['card_type'] ?? '');
if (strpos($cardType, 'halo') !== false || strpos($cardType, 'telkomsel') !== false) {
    $isHalo = true;
}

// Hitung Usage & Persentase
$totalFlow = floatval($sim['total_flow']);
$usedFlow = floatval($sim['used_flow']);
$remainingFlow = max(0, $totalFlow - $usedFlow);

$percentage = ($totalFlow > 0) ? ($usedFlow / $totalFlow) * 100 : 0;
$pct_display = min(100, $percentage);

// Tentukan warna Bar
$barColor = 'bg-emerald-500';
$glowColor = 'shadow-emerald-500/50';
$textColor = 'text-emerald-500';
$cardColor = 'bg-emerald-50 border-emerald-200 text-emerald-700';
if ($percentage >= 90) {
    $barColor = 'bg-red-500';
    $glowColor = 'shadow-red-500/50';
    $textColor = 'text-red-500';
    $cardColor = 'bg-red-50 border-red-200 text-red-700';
} elseif ($percentage >= 70) {
    $barColor = 'bg-amber-500';
    $glowColor = 'shadow-amber-500/50';
    $textColor = 'text-amber-500';
    $cardColor = 'bg-amber-50 border-amber-200 text-amber-700';
}

// --- 4. FETCH HISTORY DATA ---
$msisdn_safe = $conn->real_escape_string($sim['msisdn']);
$histSql = "SELECT used_flow, recorded_at FROM sim_usage_history WHERE iccid = '$iccid_req' OR msisdn = '$msisdn_safe' ORDER BY recorded_at DESC LIMIT 20";
$histRes = $conn->query($histSql);
$history = [];
while ($h = $histRes->fetch_assoc()) {
    $history[] = $h;
}
$last_update = !empty($history) ? date('d M Y, H:i', strtotime($history[0]['recorded_at'])) : 'Never Updated';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIM Detail - <?= htmlspecialchars($sim['msisdn'] ?? $sim['iccid']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: { 
                    fontFamily: { 
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    },
                    colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A', tselred: '#E02020' },
                    animation: { 
                        'fade-in-up': 'fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards',
                        'progress-fill': 'progressFill 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards'
                    },
                    keyframes: { 
                        fadeInUp: { '0%': { opacity: '0', transform: 'translateY(20px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                        progressFill: { '0%': { width: '0%' } }
                    }
                }
            }
        }
    </script>
    <style>
        .timeline-dot::before {
            content: ''; position: absolute; left: 5px; top: 24px; bottom: -24px; width: 2px;
            background-color: #E2E8F0; z-index: 0;
        }
        .dark .timeline-dot::before { background-color: #334155; }
        .timeline-item:last-child .timeline-dot::before { display: none; }
        
        /* === 3D FLIP CARD LOGIC === */
        .scene { perspective: 1500px; width: 100%; aspect-ratio: 1.58 / 1; max-width: 420px; margin: 0 auto; cursor: pointer; }
        .card-container {
            width: 100%; height: 100%; position: relative;
            transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border-radius: 20px;
        }
        .scene.is-flipped .card-container { transform: rotateY(180deg); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        
        .card-face {
            position: absolute; width: 100%; height: 100%;
            -webkit-backface-visibility: hidden; backface-visibility: hidden;
            border-radius: 20px; overflow: hidden;
        }
        .card-front { background-color: #ffffff; }
        .card-back { background-color: #ffffff; transform: rotateY(180deg); }

        /* General SIM Styling */
        .sim-chip-gradient { background: linear-gradient(135deg, #FCE69B 0%, #D99532 100%); }
        .chip-lines {
            background-image: linear-gradient(90deg, rgba(160,80,10,0.3) 1px, transparent 1px),
                              linear-gradient(0deg, rgba(160,80,10,0.3) 1px, transparent 1px);
            background-size: 16px 16px;
        }
        .cut-line { stroke-dasharray: 6 4; stroke: #cbd5e1; stroke-width: 2; fill: none; }
        
        /* Barcode Generator (CSS Only) */
        .barcode-stripes {
            background-image: repeating-linear-gradient(90deg, #1e293b, #1e293b 2px, transparent 2px, transparent 4px, #1e293b 4px, #1e293b 5px, transparent 5px, transparent 8px, #1e293b 8px, #1e293b 12px, transparent 12px, transparent 14px);
        }
    </style>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans">
    
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden scroll-smooth">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 md:p-8 max-w-7xl mx-auto w-full">
                
                <div class="mb-8 animate-fade-in-up">
                    <nav class="flex text-xs font-bold text-slate-400 mb-2 gap-2 items-center tracking-wide">
                        <a href="sim-list" class="hover:text-primary transition-colors">SIM Management</a>
                        <i class="ph ph-caret-right text-[10px]"></i>
                        <span class="text-slate-600 dark:text-slate-200">SIM Detail</span>
                    </nav>
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-3">
                            <span class="bg-gradient-to-r from-primary to-purple-600 bg-clip-text text-transparent">SIM Detail</span>
                        </h2>
                        <a href="sim-list" class="px-4 py-2 bg-white dark:bg-darkcard border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:shadow-md transition-all flex items-center gap-2">
                            <i class="ph ph-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <div class="lg:col-span-4 space-y-8 animate-fade-in-up" style="animation-delay: 0.1s;">
                        
                        <div class="scene" id="simCard3D" onclick="this.classList.toggle('is-flipped')">
                            <div class="card-container">
                                
                                <div class="card-face card-front border border-slate-200 dark:border-slate-700 relative <?= $isHalo ? 'bg-white' : 'bg-slate-50 dark:bg-slate-800' ?>">
                                    
                                    <?php if($isHalo): ?>
                                        <div class="absolute -right-8 top-1/2 -translate-y-1/2 opacity-[0.03] scale-150 pointer-events-none">
                                            <svg width="200" height="200" viewBox="0 0 100 100"><path d="M50 0 Q100 50 50 100 Q0 50 50 0" fill="#000"/></svg>
                                        </div>
                                        <div class="absolute -right-4 top-1/2 -translate-y-1/2 opacity-[0.06] scale-125 pointer-events-none">
                                            <svg width="200" height="200" viewBox="0 0 100 100"><path d="M50 0 Q100 50 50 100 Q0 50 50 0" fill="#000"/></svg>
                                        </div>

                                        <svg class="absolute left-5 top-1/2 -translate-y-1/2 w-[120px] h-[90px]" style="z-index: 1;">
                                            <rect x="2" y="2" width="116" height="86" rx="12" class="cut-line" />
                                            <path d="M 118 65 L 95 88" stroke="#cbd5e1" stroke-width="2" fill="none" stroke-dasharray="6 4"/>
                                        </svg>

                                        <div class="absolute left-[34px] top-1/2 -translate-y-1/2 z-10 w-[70px] h-[54px] sim-chip-gradient rounded-md border border-amber-600/40 overflow-hidden shadow-sm flex items-center justify-center">
                                            <div class="w-full h-full chip-lines relative">
                                                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-8 h-8 border border-amber-800/20 rounded-full"></div>
                                                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-px bg-amber-800/20"></div>
                                                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-full w-px bg-amber-800/20"></div>
                                            </div>
                                        </div>

                                        <div class="absolute bottom-5 left-6 z-10">
                                            <p class="text-tselred font-bold text-sm tracking-tight leading-none mb-0.5">Telkomsel</p>
                                            <p class="text-tselred font-extrabold text-3xl tracking-tighter leading-none">Halo</p>
                                        </div>
                                        
                                        <div class="absolute top-4 right-5 opacity-40 flex items-center gap-1.5 animate-pulse">
                                            <i class="ph ph-hand-tap text-lg"></i>
                                            <span class="text-[8px] font-bold uppercase tracking-widest">Flip</span>
                                        </div>

                                    <?php else: ?>
                                        <div class="absolute left-6 top-1/2 -translate-y-1/2 w-[110px] h-[85px] border-[3px] border-slate-300 dark:border-slate-600 rounded-xl border-dashed"></div>
                                        <div class="absolute left-[33px] top-1/2 -translate-y-1/2 z-10 w-24 h-16 sim-chip-gradient rounded-md shadow-sm border border-amber-600/40 overflow-hidden">
                                            <div class="w-full h-full chip-lines relative">
                                                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-10 h-10 border border-amber-700/30 rounded-full"></div>
                                            </div>
                                        </div>
                                        <div class="absolute right-6 top-6 bottom-6 flex flex-col justify-between text-right z-10 w-1/2">
                                            <div>
                                                <span class="inline-block px-2.5 py-1 bg-slate-800 dark:bg-slate-200 text-white dark:text-slate-900 font-bold text-[9px] uppercase tracking-widest rounded-md shadow-sm">
                                                    <?= htmlspecialchars($sim['card_type'] ?: 'STANDARD SIM') ?>
                                                </span>
                                            </div>
                                            <div class="space-y-1.5">
                                                <div>
                                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-0.5">MSISDN</p>
                                                    <p class="text-sm font-mono font-bold tracking-wider text-slate-700 dark:text-white select-all"><?= htmlspecialchars($sim['msisdn'] ?: 'UNKNOWN') ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="absolute bottom-0 right-0 w-32 h-32 bg-primary/10 rounded-tl-full blur-2xl pointer-events-none"></div>
                                    <?php endif; ?>
                                </div>

                                <div class="card-face card-back border border-slate-200 dark:border-slate-700 relative <?= $isHalo ? 'bg-white' : 'bg-slate-100 dark:bg-slate-900' ?>">
                                    <?php if($isHalo): ?>
                                        <div class="absolute top-4 left-5">
                                            <p class="text-[11px] text-slate-600 font-medium">Call center <span class="bg-slate-500 text-white px-1.5 py-0.5 rounded-full font-bold text-[9px]">188</span></p>
                                            <p class="text-[10px] text-slate-500">www.telkomsel.com/halo</p>
                                        </div>

                                        <div class="absolute top-5 right-5 w-[140px] h-[18px] barcode-stripes opacity-70"></div>
                                        <div class="absolute top-10 right-5 w-[140px] text-center tracking-widest font-mono text-[8px] text-slate-500"><?= htmlspecialchars($sim['iccid']) ?></div>

                                        <div class="absolute right-8 top-1/2 -translate-y-1/2 w-[100px] h-[60px] border-2 border-slate-200 rounded-lg flex items-center p-2 shadow-inner">
                                            <div class="text-[8px] font-mono leading-tight text-slate-500 select-all pr-2 border-r border-slate-200 w-1/2 break-all">
                                                <?= wordwrap(htmlspecialchars($sim['iccid']), 4, "<br>", true) ?>
                                            </div>
                                            <div class="pl-2 w-1/2">
                                                <p class="text-tselred font-bold text-[7px] leading-none mb-0.5">Telkomsel</p>
                                                <p class="text-tselred font-extrabold text-sm leading-none tracking-tight">Halo</p>
                                            </div>
                                        </div>

                                        <div class="absolute left-5 top-1/2 -translate-y-1/2 w-[180px] space-y-2">
                                            <div class="bg-slate-50 border border-slate-100 p-2 rounded-lg">
                                                <p class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">Customer</p>
                                                <p class="text-[11px] font-bold text-slate-800 leading-tight truncate"><?= htmlspecialchars($sim['company_name'] ?? 'Unknown Company') ?></p>
                                            </div>
                                            <div class="bg-slate-50 border border-slate-100 p-2 rounded-lg flex justify-between items-center">
                                                <div>
                                                    <p class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">MSISDN</p>
                                                    <p class="text-xs font-mono font-bold text-slate-800"><?= htmlspecialchars($sim['msisdn']) ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">Package</p>
                                                    <p class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded dynamic-val" data-bytes="<?= $totalFlow ?>"><?= formatBytesMB($totalFlow) ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="absolute bottom-3 left-5 right-5 flex justify-between items-end">
                                            <p class="text-[7px] text-slate-400 leading-tight w-[60%]">
                                                Dengan digunakannya kartu SIM ini, pengguna sepakat untuk tunduk kepada syarat dan ketentuan yang ditetapkan oleh Telkomsel dari waktu ke waktu.
                                            </p>
                                            <p class="text-[10px] font-bold text-slate-800">by Telkom Indonesia</p>
                                        </div>

                                    <?php else: ?>
                                        <div class="p-6 h-full flex flex-col justify-between">
                                            <div class="flex justify-between items-start">
                                                <div class="w-32 h-6 barcode-stripes opacity-40"></div>
                                                <span class="text-[10px] font-mono text-slate-400 select-all"><?= htmlspecialchars($sim['iccid']) ?></span>
                                            </div>
                                            <div class="space-y-2 bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                                                <div>
                                                    <p class="text-[8px] text-slate-400 font-bold uppercase tracking-wider">Customer</p>
                                                    <p class="text-xs font-bold text-slate-800 dark:text-white truncate"><?= htmlspecialchars($sim['company_name'] ?? 'Unknown Company') ?></p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <div>
                                                        <p class="text-[8px] text-slate-400 font-bold uppercase tracking-wider">MSISDN</p>
                                                        <p class="text-xs font-mono font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($sim['msisdn']) ?></p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-[8px] text-slate-400 font-bold uppercase tracking-wider">Package</p>
                                                        <p class="text-xs font-bold text-primary dynamic-val" data-bytes="<?= $totalFlow ?>"><?= formatBytesMB($totalFlow) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Ownership Info</h3>
                            
                            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100 dark:border-slate-700">
                                <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-primary flex items-center justify-center font-bold text-lg border border-indigo-100 dark:border-indigo-800 flex-shrink-0">
                                    <?= strtoupper(substr($sim['company_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-white text-lg leading-tight mb-1"><?= htmlspecialchars($sim['company_name'] ?? 'Unknown Company') ?></p>
                                    <p class="text-xs text-slate-500">Tier Level: <span class="font-bold text-primary">L<?= htmlspecialchars($sim['level'] ?? '1') ?></span></p>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">Project</p>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($sim['custom_project'] ?: 'Default Project') ?></p>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">Batch</p>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($sim['batch'] ?: '-') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">Expired Date</p>
                                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                            <?= !empty($sim['expired_date']) ? date('d M Y', strtotime($sim['expired_date'])) : '-' ?>
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">Serial Number (SN)</p>
                                    <p class="text-sm font-mono text-slate-700 dark:text-slate-200 select-all"><?= htmlspecialchars($sim['sn'] ?: '-') ?></p>
                                </div>
                                
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">IMSI</p>
                                    <p class="text-sm font-mono text-slate-700 dark:text-slate-200 select-all"><?= htmlspecialchars($sim['imsi'] ?: '-') ?></p>
                                </div>
                                
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">Invoice No</p>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200 select-all"><?= htmlspecialchars($sim['invoice_number'] ?: '-') ?></p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="lg:col-span-8 space-y-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                        
                        <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 md:p-8 relative overflow-hidden">
                            <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

                            <div class="flex flex-col sm:flex-row justify-between items-start mb-8 relative z-10 gap-4">
                                <div>
                                    <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <i class="ph-fill ph-chart-donut text-primary text-2xl"></i> Usage Analytics
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-1">Last Update: <strong class="text-slate-700 dark:text-slate-300"><?= $last_update ?></strong></p>
                                </div>
                                <div class="flex items-end gap-6 text-right">
                                    <div class="text-left">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Unit</p>
                                        <select id="detailUnitSelector" onchange="updateUsageUnits()" class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-lg px-2 py-1 outline-none focus:ring-1 focus:ring-primary cursor-pointer transition-all">
                                            <option value="KB">KB</option>
                                            <option value="MB" selected>MB</option>
                                            <option value="GB">GB</option>
                                        </select>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Status</p>
                                        <div class="<?= $textColor ?> font-extrabold text-3xl drop-shadow-sm flex items-center justify-end gap-1 leading-none">
                                            <?= number_format($pct_display, 1) ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-10 relative z-10">
                                <div class="flex justify-between text-xs font-bold text-slate-500 mb-2">
                                    <span>0 <span class="unit-label">MB</span></span>
                                    <span class="dynamic-val" data-bytes="<?= $totalFlow ?>"><?= formatBytesMB($totalFlow) ?></span>
                                </div>
                                <div class="w-full h-4 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden shadow-inner border border-slate-200 dark:border-slate-700">
                                    <div class="h-full <?= $barColor ?> shadow-[0_0_15px] <?= $glowColor ?> rounded-full relative animate-progress-fill" style="width: <?= $pct_display ?>%;">
                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent -translate-x-full animate-[shimmer_2s_infinite]"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative z-10">
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-5 border border-slate-100 dark:border-slate-700">
                                    <i class="ph ph-database text-2xl text-slate-400 mb-2"></i>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Package</p>
                                    <p class="text-xl font-bold text-slate-800 dark:text-white mt-1 dynamic-val" data-bytes="<?= $totalFlow ?>"><?= formatBytesMB($totalFlow) ?></p>
                                </div>
                                <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl p-5 border border-indigo-100 dark:border-indigo-800/50">
                                    <i class="ph ph-trend-up text-2xl text-primary mb-2"></i>
                                    <p class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest">Total Used</p>
                                    <p class="text-xl font-bold text-primary dark:text-indigo-300 mt-1 dynamic-val" data-bytes="<?= $usedFlow ?>"><?= formatBytesMB($usedFlow) ?></p>
                                </div>
                                <div class="rounded-2xl p-5 border <?= $cardColor ?> bg-opacity-30 dark:bg-opacity-10">
                                    <i class="ph ph-check-circle text-2xl mb-2"></i>
                                    <p class="text-[10px] font-bold uppercase tracking-widest opacity-80">Remaining</p>
                                    <p class="text-xl font-bold mt-1 dynamic-val" data-bytes="<?= $remainingFlow ?>"><?= formatBytesMB($remainingFlow) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-6 md:p-8">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-6">
                                <i class="ph ph-clock-counter-clockwise text-primary"></i> Usage Update History
                            </h3>

                            <?php if (empty($history)): ?>
                                <div class="text-center py-10 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                                    <div class="w-16 h-16 bg-white dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm text-slate-400">
                                        <i class="ph ph-ghost text-3xl"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">No History Found</h4>
                                    <p class="text-xs text-slate-500 mt-1">Usage data has never been updated via Excel upload.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-0 pl-2">
                                    <?php foreach ($history as $idx => $hist): 
                                        $isLatest = ($idx === 0);
                                        $used = floatval($hist['used_flow']);
                                        $hPct = ($totalFlow > 0) ? ($used / $totalFlow) * 100 : 0;
                                    ?>
                                    <div class="relative pl-8 py-3 timeline-item group">
                                        <div class="absolute left-0 top-4 w-3 h-3 rounded-full timeline-dot z-10 <?= $isLatest ? 'bg-primary shadow-[0_0_10px] shadow-indigo-500/50 ring-4 ring-indigo-50 dark:ring-indigo-900/50' : 'bg-slate-300 dark:bg-slate-600 group-hover:bg-slate-400 transition-colors' ?>"></div>
                                        
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                            <div>
                                                <p class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                                    <span class="dynamic-val" data-bytes="<?= $used ?>"><?= formatBytesMB($used) ?></span>
                                                    <?php if($isLatest): ?>
                                                        <span class="text-[9px] bg-primary text-white px-1.5 py-0.5 rounded uppercase tracking-wider">Latest</span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-slate-500 font-mono mt-0.5">
                                                    <i class="ph ph-calendar-blank"></i> <?= date('d M Y - H:i:s', strtotime($hist['recorded_at'])) ?>
                                                </p>
                                            </div>
                                            <div class="text-right flex items-center gap-3">
                                                <span class="text-xs font-bold text-slate-400"><?= number_format($hPct, 1) ?>%</span>
                                                <div class="w-20 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                                    <div class="h-full bg-slate-400 dark:bg-slate-500 rounded-full" style="width: <?= min($hPct, 100) ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>

            </main>
        </div>
    </div>

    <style>
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>
    
    <script>
        function updateUsageUnits() {
            const unit = document.getElementById('detailUnitSelector').value;
            const elements = document.querySelectorAll('.dynamic-val');
            const unitLabels = document.querySelectorAll('.unit-label');
            
            unitLabels.forEach(lbl => {
                lbl.innerText = unit;
            });

            elements.forEach(el => {
                const rawBytes = parseFloat(el.getAttribute('data-bytes'));
                if(isNaN(rawBytes)) return;

                const baseMB = rawBytes / 1048576; 

                let val = 0;
                if(unit === 'KB') {
                    val = baseMB * 1000;
                } else if(unit === 'MB') {
                    val = baseMB;
                } else if(unit === 'GB') {
                    val = baseMB / 1000;
                }

                el.innerText = val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + unit;
            });
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>