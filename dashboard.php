<?php 
include 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// --- Helper: Format Bytes (Smart Decimal) ---
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) { 
        // Handle 0
        if ($bytes <= 0 || $bytes === null || is_nan($bytes)) return '0 MB';

        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $bytes = max($bytes, 0);
        
        // Kalkulasi Basis 1024
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        // Format angka dengan ribuan separator
        $number = number_format($bytes, $precision);

        // Hapus .00 jika ada di belakang agar tampilan bersih
        if (substr($number, -3) === '.00') {
            $number = substr($number, 0, -3);
        }

        return $number . ' ' . $units[$pow];
    }
}

// --- DATA ACCESS CONTROL ---
$allowed_comps = getClientIdsForUser($user_id);
$sim_where = "";
$comp_where = "";

if ($allowed_comps === 'NONE') {
    $sim_where = " AND 1=0 "; 
    $comp_where = " AND 1=0 ";
} elseif (is_array($allowed_comps)) {
    $ids = implode(',', $allowed_comps);
    // Filter company_id tanpa prefix tabel agar aman untuk semua query
    $sim_where = " AND company_id IN ($ids) "; 
    $comp_where = " AND id IN ($ids) ";
} 

// --- METRICS ---
// 1. Total SIMs
$q = $conn->query("SELECT COUNT(*) as t FROM sims WHERE 1=1 $sim_where");
$totalSims = $q->fetch_assoc()['t'];

// 2. Total Usage
$q = $conn->query("SELECT SUM(used_flow) as t FROM sims WHERE 1=1 $sim_where");
$totalUsageBytes = $q->fetch_assoc()['t'] ?? 0;
$totalUsageFormatted = formatBytes($totalUsageBytes);

// 3. Total Companies
$q = $conn->query("SELECT COUNT(*) as t FROM companies WHERE 1=1 $comp_where");
$totalComp = $q->fetch_assoc()['t'];

// --- CHARTS DATA ---

// Chart 1: Top 5 Companies Usage (Bar Chart)
$chartLabels = [];
$chartData = [];
$sqlC = "SELECT c.company_name, SUM(s.used_flow) as usage_sum 
         FROM sims s JOIN companies c ON s.company_id = c.id 
         WHERE 1=1 $sim_where 
         GROUP BY c.id ORDER BY usage_sum DESC LIMIT 5";
$resC = $conn->query($sqlC);
while($r = $resC->fetch_assoc()) {
    $chartLabels[] = $r['company_name'];
    // Data untuk chart bar dalam MB (float)
    $chartData[] = round(($r['usage_sum'] ?? 0) / 1048576, 2); 
}

// Chart 2: Package Distribution (Donut Chart)
$pkgLabels = [];
$pkgData = [];
$sqlP = "SELECT total_flow, COUNT(*) as total 
         FROM sims 
         WHERE 1=1 $sim_where 
         GROUP BY total_flow 
         ORDER BY total_flow DESC";
$resP = $conn->query($sqlP);
while($r = $resP->fetch_assoc()) {
    $label = formatBytes($r['total_flow']);
    if($r['total_flow'] <= 0) {
        $label = "No Package";
    }
    $pkgLabels[] = $label;
    $pkgData[] = (int)$r['total'];
}

// List: Top 10 Highest Usage SIMs (UPDATED LIMIT TO 10)
$topSims = [];
$sqlTop = "SELECT s.msisdn, s.iccid, s.used_flow, s.total_flow, c.company_name 
           FROM sims s JOIN companies c ON s.company_id = c.id 
           WHERE 1=1 $sim_where 
           ORDER BY s.used_flow DESC LIMIT 10";
$resTop = $conn->query($sqlTop);
while($r = $resTop->fetch_assoc()) {
    $topSims[] = $r;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Usage Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A' },
                    animation: { 'fade-in': 'fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards' },
                    keyframes: { fadeIn: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-darkbg text-slate-600 dark:text-slate-300 font-sans antialiased overflow-hidden">
    
    <div class="flex h-screen">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 lg:p-8">
                
                <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4 animate-fade-in">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Dashboard</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Overview of your Usage Monitor.</p>
                    </div>
                    <div class="px-4 py-2 bg-white dark:bg-darkcard rounded-full border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 shadow-sm">
                        <i class="ph ph-user text-primary mr-1"></i> <?= $_SESSION['username'] ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 animate-fade-in" style="animation-delay: 0.1s;">
                    
                    <div class="relative overflow-hidden bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 group">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-500/10 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                    <i class="ph ph-sim-card text-xl"></i>
                                </div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Cards</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white"><?= number_format($totalSims) ?></h3>
                            <p class="text-xs text-slate-400 mt-1">Registered SIMs</p>
                        </div>
                    </div>

                    <div class="relative overflow-hidden bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 group">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/10 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                    <i class="ph ph-chart-line-up text-xl"></i>
                                </div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Usage</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white"><?= $totalUsageFormatted ?></h3>
                            <p class="text-xs text-slate-400 mt-1">Accumulated Data</p>
                        </div>
                    </div>

                    <div class="relative overflow-hidden bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 group">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-orange-500/10 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-xl bg-orange-50 dark:bg-orange-500/20 flex items-center justify-center text-orange-600 dark:text-orange-400">
                                    <i class="ph ph-buildings text-xl"></i>
                                </div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Clients</span>
                            </div>
                            <h3 class="text-3xl font-bold text-slate-900 dark:text-white"><?= $totalComp ?></h3>
                            <p class="text-xs text-slate-400 mt-1">Managed Companies</p>
                        </div>
                    </div>

                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in" style="animation-delay: 0.2s;">
                    
                    <div class="lg:col-span-2 space-y-6">
                        
                        <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                             <div class="flex justify-between items-center mb-6">
                                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <i class="ph ph-package text-emerald-500"></i> Package Distribution
                                </h3>
                            </div>
                            <?php if(empty($pkgData) || array_sum($pkgData) === 0): ?>
                                <div class="h-[300px] flex items-center justify-center text-slate-400 text-sm border-2 border-dashed border-slate-100 dark:border-slate-700 rounded-xl">
                                    No package data available
                                </div>
                            <?php else: ?>
                                <div id="chartPackage" class="w-full h-[300px] flex justify-center"></div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-white dark:bg-darkcard p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <i class="ph ph-trend-up text-primary"></i> Top Usage by Company
                                </h3>
                                <span class="text-xs text-slate-400 font-medium">Unit: MB</span>
                            </div>
                            <div id="chartUsage" class="w-full h-[300px]"></div>
                        </div>

                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white dark:bg-darkcard rounded-2xl shadow-lg border border-slate-100 dark:border-slate-700 h-full flex flex-col">
                            <div class="p-6 border-b border-slate-100 dark:border-slate-700">
                                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    <i class="ph ph-lightning text-yellow-500"></i> Highest Usage
                                </h3>
                                <p class="text-xs text-slate-400 mt-1">Top 10 SIM cards by consumption.</p>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto p-4 space-y-4 max-h-[600px] scrollbar-thin">
                                <?php if (empty($topSims)): ?>
                                    <div class="text-center py-10 text-slate-400 text-sm">No usage data available.</div>
                                <?php else: foreach($topSims as $idx => $sim): 
                                    $used = $sim['used_flow'] ?? 0;
                                    $total = $sim['total_flow'] ?? 0;
                                    $pct = ($total > 0) ? ($used / $total) * 100 : 0;
                                    $barColor = $pct > 90 ? 'bg-red-500' : ($pct > 70 ? 'bg-yellow-500' : 'bg-emerald-500');
                                ?>
                                <div class="group p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-800 hover:shadow-md transition-all border border-transparent hover:border-slate-100 dark:hover:border-slate-700">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-200"><?= $sim['msisdn'] ?: 'N/A' ?></span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-500">#<?= $idx+1 ?></span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[150px]" title="<?= $sim['company_name'] ?>"><?= $sim['company_name'] ?: 'Unknown' ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs font-bold text-primary"><?= formatBytes($used) ?></p>
                                            <p class="text-[9px] text-slate-400">of <?= formatBytes($total) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="relative h-1.5 w-full bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                        <div class="absolute top-0 left-0 h-full <?= $barColor ?> rounded-full transition-all duration-1000" style="width: <?= min($pct, 100) ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>

                            <div class="p-4 border-t border-slate-100 dark:border-slate-700">
                                <a href="sim-list" class="flex items-center justify-center w-full py-2.5 text-xs font-bold text-primary bg-indigo-50 dark:bg-indigo-900/20 rounded-xl hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition-colors">
                                    View All SIMs
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // --- CHART 1: Top Usage (Bar) ---
        var optionsUsage = {
            series: [{ name: 'Data Usage', data: <?= json_encode($chartData) ?> }],
            chart: { type: 'bar', height: 300, toolbar: {show: false}, fontFamily: 'Plus Jakarta Sans, sans-serif' },
            colors: ['#4F46E5'],
            plotOptions: { bar: { borderRadius: 6, horizontal: false, columnWidth: '40%' } },
            xaxis: { 
                categories: <?= json_encode($chartLabels) ?>, 
                labels: { style: { colors: '#94a3b8', fontSize: '11px', fontWeight: 500 } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: { labels: { style: { colors: '#94a3b8' } } },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { top: 0, right: 0, bottom: 0, left: 10 } },
            dataLabels: { enabled: false },
            tooltip: { 
                theme: 'light', 
                y: { formatter: function (val) { return val + " MB" } },
                style: { fontSize: '12px' }
            },
            fill: { opacity: 1 }
        };
        
        if(document.querySelector("#chartUsage")) {
            var chartUsage = new ApexCharts(document.querySelector("#chartUsage"), optionsUsage);
            chartUsage.render();
        }

        // --- CHART 2: Package Distribution (Donut) ---
        var optionsPkg = {
            series: <?= json_encode($pkgData) ?>,
            labels: <?= json_encode($pkgLabels) ?>,
            chart: { type: 'donut', height: 320, fontFamily: 'Plus Jakarta Sans, sans-serif' },
            colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total SIMs',
                                fontSize: '12px',
                                fontWeight: 500,
                                color: '#94a3b8',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                }
                            },
                            value: {
                                fontSize: '24px',
                                fontWeight: 700,
                                color: '#1e293b',
                               offsetY: 6,
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['#fff'] },
            legend: { 
                position: 'bottom', 
                horizontalAlign: 'center', 
                fontSize: '12px', 
                markers: { radius: 12 },
                itemMargin: { horizontal: 10, vertical: 5 }
            },
            tooltip: { 
                theme: 'light',
                y: {
                    formatter: function(val) {
                        return val + " SIMs"
                    }
                }
            }
        };

        if(document.querySelector("#chartPackage") && optionsPkg.series.length > 0) {
            var chartPkg = new ApexCharts(document.querySelector("#chartPackage"), optionsPkg);
            chartPkg.render();
        }
    </script>
</body>
</html>