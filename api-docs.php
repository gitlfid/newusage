<?php 
include 'config.php';
checkLogin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web@2.0.3"></script>
    <link rel="stylesheet" href="assets/css/style.css">
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
                            <i class="ph ph-book-open-text text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">API Documentation</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Comprehensive guide to integrate with our IoT Platform.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="ph ph-shield-check text-primary"></i> Authentication Overview
                        </h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm mb-4 leading-relaxed">
                            Our API uses APISIX Gateway for routing and security. Most endpoints require authentication via custom HTTP Headers. You must generate your <strong>Access Key</strong> and <strong>Secret Key</strong> using the Self-Service API or request them from your Administrator.
                        </p>
                        <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Required Headers (For Protected Endpoints)</p>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs font-bold text-primary bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded">X-Access-Key</span>
                                    <span class="text-sm">Your unique 32-character Access Key.</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs font-bold text-primary bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded">X-Secret-Key</span>
                                    <span class="text-sm">Your secure 64-character Secret Key.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-6 animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-wrap items-center justify-between gap-4">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">1. Self-Service API Key Generation</h3>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-bold text-[11px] rounded uppercase tracking-wide">POST</span>
                            <code class="text-sm font-bold text-slate-600 dark:text-slate-300">/api/v1/generate_keys.php</code>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm mb-6">Generates a new set of Access Key and Secret Key for your account. You only need your <code>User Code</code> (e.g., LFID-XXXX) to generate keys. <strong class="text-amber-500">Note: You can only generate keys once. If you lose them, you must ask an admin to revoke the old key first.</strong></p>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Request Example (cURL)</h4>
                                <div class="bg-[#1E1E1E] text-slate-300 p-4 rounded-xl text-xs font-mono overflow-x-auto">
curl -X POST "http://[YOUR_SERVER_IP]:6012/api/v1/generate_keys.php" \
     -H "Content-Type: application/json" \
     -d '{"user_code": "LFID-XXXXXX"}'
                                </div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mt-5 mb-2">Body Parameters</h4>
                                <table class="w-full text-left text-sm border-collapse">
                                    <tr class="border-b border-slate-100 dark:border-slate-700">
                                        <td class="py-2 font-mono text-xs text-primary">user_code</td>
                                        <td class="py-2 text-xs text-slate-500">Required. String. Your unique account code.</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Success Response (201 Created)</h4>
                                <div class="bg-[#1E1E1E] text-emerald-400 p-4 rounded-xl text-xs font-mono overflow-x-auto whitespace-pre">
{
  "status": "success",
  "message": "API Keys generated successfully",
  "data": {
    "user_code": "LFID-3FE631",
    "username": "indonesia",
    "access_key": "c3f8e91d5a7b2c4e6f8a...",
    "secret_key": "a1b2c3d4e5f6a7b8c9d0..."
  }
}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-6 animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-wrap items-center justify-between gap-4">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">2. Retrieve SIM Information & Usage</h3>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 font-bold text-[11px] rounded uppercase tracking-wide">GET</span>
                            <code class="text-sm font-bold text-slate-600 dark:text-slate-300">/api/v1/sim.php</code>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-sm mb-6">Fetches details and real-time data usage of SIM cards. It strictly follows your account's access scope. If you pass an MSISDN, it returns that specific SIM object. If no MSISDN is passed, it returns a list/array of all SIM cards assigned to your companies.</p>
                        
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Request Example (cURL)</h4>
                                <div class="bg-[#1E1E1E] text-slate-300 p-4 rounded-xl text-xs font-mono overflow-x-auto">
curl -X GET "http://[YOUR_SERVER_IP]:6012/api/v1/sim.php?msisdn=628111811844" \
     -H "X-Access-Key: YOUR_ACCESS_KEY" \
     -H "X-Secret-Key: YOUR_SECRET_KEY"
                                </div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mt-5 mb-2">Query Parameters</h4>
                                <table class="w-full text-left text-sm border-collapse">
                                    <tr class="border-b border-slate-100 dark:border-slate-700">
                                        <td class="py-2 font-mono text-xs text-primary">msisdn</td>
                                        <td class="py-2 text-xs text-slate-500">Optional. String. Filter by specific SIM number.</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Success Response (200 OK)</h4>
                                <div class="bg-[#1E1E1E] text-emerald-400 p-4 rounded-xl text-xs font-mono overflow-x-auto whitespace-pre">
{
  "status": "success",
  "total_data": 1,
  "data": {
    "customer": "PT Linksfield Networks Indonesia",
    "msisdn": "628111811844",
    "iccid": "896289871867621055",
    "imsi": "456178219341",
    "sn": "00013010883871844",
    "data_package": {
      "raw_bytes": 1048576000,
      "formatted": "1,000.00 MB"
    },
    "usage": {
      "raw_bytes": 104857600,
      "formatted": "100.00 MB"
    }
  }
}
                                </div>
                                <p class="text-[11px] text-slate-400 mt-2 italic">*Note: If MSISDN parameter is omitted, the "data" object will be an array [ ... ] containing multiple SIM records.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>