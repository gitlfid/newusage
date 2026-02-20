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
    <style>
        /* Custom Scrollbar untuk Code Blocks */
        .code-scroll::-webkit-scrollbar { height: 6px; width: 6px; }
        .code-scroll::-webkit-scrollbar-track { background: #1E293B; border-radius: 4px; }
        .code-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        .code-scroll::-webkit-scrollbar-thumb:hover { background: #64748B; }
    </style>
    <script>
        tailwind.config = { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }, 
                    colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A' }, 
                    animation: { 'fade-in-up': 'fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards' }, 
                    keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(20px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } } } 
                } 
            } 
        }
    </script>
</head>
<body class="bg-[#F8FAFC] dark:bg-darkbg text-slate-600 dark:text-slate-300 antialiased font-sans">
    
    <div id="copyToast" class="fixed top-5 right-5 z-[100] transform transition-all duration-300 translate-x-full opacity-0 flex items-center gap-3 bg-slate-800 text-white shadow-2xl rounded-xl p-4 pr-6 border-l-4 border-emerald-500">
        <div class="p-1.5 bg-emerald-500/20 rounded-full text-emerald-400">
            <i class="ph ph-check-circle text-xl"></i>
        </div>
        <div>
            <p class="text-sm font-bold">Copied to Clipboard</p>
            <p class="text-[11px] text-slate-400">The code has been successfully copied.</p>
        </div>
    </div>

    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden scroll-smooth">
            <?php include 'includes/header.php'; ?>
            
            <main class="flex-1 p-6 md:p-8 max-w-7xl mx-auto w-full">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10 animate-fade-in-up">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl text-white shadow-lg shadow-indigo-500/30">
                            <i class="ph ph-brackets-curly text-3xl"></i>
                        </div>
                        <div>
                            <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-slate-900 to-slate-600 dark:from-white dark:to-slate-400 tracking-tight">API Documentation</h2>
                            <p class="text-sm text-slate-500 mt-1">Seamlessly integrate your systems with our IoT Platform.</p>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center gap-2 bg-white dark:bg-darkcard px-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 shadow-sm">
                        <span class="relative flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">API Gateway Online</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 dark:border-slate-800 overflow-hidden mb-8 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2.5">
                            <i class="ph ph-shield-check text-xl text-primary"></i> Authentication
                        </h3>
                    </div>
                    <div class="p-6 md:p-8">
                        <p class="text-sm mb-6 leading-relaxed text-slate-600 dark:text-slate-400">
                            Our API uses APISIX Gateway for routing and security. Protected endpoints require authentication via custom HTTP Headers. You must generate your <strong class="text-slate-800 dark:text-slate-200">Access Key</strong> and <strong class="text-slate-800 dark:text-slate-200">Secret Key</strong> using the Self-Service API or request them from your Administrator.
                        </p>
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-800/50 dark:to-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Required Headers</p>
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <span class="font-mono text-xs font-bold text-primary bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800/50 px-3 py-1.5 rounded-lg inline-block w-fit">X-Access-Key</span>
                                    <span class="text-sm text-slate-600 dark:text-slate-400">Your unique 32-character Access Key.</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <span class="font-mono text-xs font-bold text-primary bg-indigo-50 dark:bg-indigo-900/40 border border-indigo-100 dark:border-indigo-800/50 px-3 py-1.5 rounded-lg inline-block w-fit">X-Secret-Key</span>
                                    <span class="text-sm text-slate-600 dark:text-slate-400">Your secure 64-character Secret Key.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 dark:border-slate-800 overflow-hidden mb-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold text-sm">1</span>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Self-Service Key Generation</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 font-bold text-[11px] rounded-lg uppercase tracking-wide">POST</span>
                            <code class="text-sm font-mono font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">/api/v1/generate_keys</code>
                        </div>
                    </div>
                    <div class="p-6 md:p-8">
                        <p class="text-sm mb-6 text-slate-600 dark:text-slate-400">Generates a new set of Access Key and Secret Key for your account. You only need your <code>User Code</code> (e.g., LFID-XXXX) to generate keys. <strong class="text-amber-600 dark:text-amber-500">Note: Keys can only be generated once per active user.</strong></p>
                        
                        <div class="grid lg:grid-cols-2 gap-8">
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2"><i class="ph ph-paper-plane-tilt"></i> Request</h4>
                                
                                <div class="rounded-xl overflow-hidden bg-[#0F172A] border border-slate-800 shadow-inner">
                                    <div class="flex items-center justify-between px-4 py-2 bg-[#1E293B] border-b border-slate-800">
                                        <div class="flex gap-1.5">
                                            <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                                            <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                                            <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                                        </div>
                                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">BASH / cURL</span>
                                        <button onclick="copyCode('code-gen-req')" class="text-slate-400 hover:text-white transition-colors" title="Copy code"><i class="ph ph-copy text-lg"></i></button>
                                    </div>
                                    <div class="p-4 text-sm font-mono text-slate-300 overflow-x-auto code-scroll" id="code-gen-req">
<span class="text-pink-400">curl</span> <span class="text-slate-400">-X</span> <span class="text-emerald-400">POST</span> <span class="text-amber-300">"https://api.linksfield.cloud/api/v1/generate_keys"</span> \
     <span class="text-slate-400">-H</span> <span class="text-amber-300">"Content-Type: application/json"</span> \
     <span class="text-slate-400">-d</span> <span class="text-amber-300">'{"user_code": "LFID-XXXXXX"}'</span>
                                    </div>
                                </div>

                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-6 mb-3">Body Parameters</h4>
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-200 dark:border-slate-700">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs font-bold text-primary">user_code</span>
                                            <span class="text-[10px] bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400 px-2 py-0.5 rounded font-bold uppercase">Required</span>
                                        </div>
                                        <span class="text-xs font-mono text-slate-400">String</span>
                                    </div>
                                    <div class="pt-3 text-sm text-slate-600 dark:text-slate-400">Your unique account code (e.g., LFID-3LL610).</div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2"><i class="ph ph-check-circle"></i> Success Response</h4>
                                
                                <div class="rounded-xl overflow-hidden bg-[#0F172A] border border-slate-800 shadow-inner h-full max-h-[350px] flex flex-col">
                                    <div class="flex items-center justify-between px-4 py-2 bg-[#1E293B] border-b border-slate-800">
                                        <span class="text-[10px] font-mono text-emerald-400 uppercase tracking-widest flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div> 201 Created</span>
                                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">JSON</span>
                                        <button onclick="copyCode('code-gen-res')" class="text-slate-400 hover:text-white transition-colors" title="Copy code"><i class="ph ph-copy text-lg"></i></button>
                                    </div>
                                    <div class="p-4 text-sm font-mono text-slate-300 overflow-y-auto code-scroll flex-1 whitespace-pre" id="code-gen-res">
{
  <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"success"</span>,
  <span class="text-blue-400">"message"</span>: <span class="text-emerald-400">"API Keys generated successfully"</span>,
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"user_code"</span>: <span class="text-amber-300">"LFID-XXXXX"</span>,
    <span class="text-blue-400">"username"</span>: <span class="text-amber-300">"indonesia"</span>,
    <span class="text-blue-400">"access_key"</span>: <span class="text-amber-300">"cf8e91d5a7b2c4e6f8a0d1b3..."</span>,
    <span class="text-blue-400">"secret_key"</span>: <span class="text-amber-300">"a2c3d4e5f6a7b8c9d0e1f2a..."</span>
  }
}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-slate-100 dark:border-slate-800 overflow-hidden mb-12 animate-fade-in-up" style="animation-delay: 0.3s;">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold text-sm">2</span>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Retrieve SIM Details</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 font-bold text-[11px] rounded-lg uppercase tracking-wide">GET</span>
                            <code class="text-sm font-mono font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">/api/v1/sim</code>
                        </div>
                    </div>
                    <div class="p-6 md:p-8">
                        <p class="text-sm mb-6 text-slate-600 dark:text-slate-400">Fetches details and real-time data usage of SIM cards based on your account's access scope. Provide an <code>msisdn</code> to get a specific record, or omit it to retrieve a list of all assigned SIMs.</p>
                        
                        <div class="grid lg:grid-cols-2 gap-8">
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2"><i class="ph ph-paper-plane-tilt"></i> Request</h4>
                                
                                <div class="rounded-xl overflow-hidden bg-[#0F172A] border border-slate-800 shadow-inner">
                                    <div class="flex items-center justify-between px-4 py-2 bg-[#1E293B] border-b border-slate-800">
                                        <div class="flex gap-1.5">
                                            <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                                            <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                                            <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                                        </div>
                                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">BASH / cURL</span>
                                        <button onclick="copyCode('code-sim-req')" class="text-slate-400 hover:text-white transition-colors" title="Copy code"><i class="ph ph-copy text-lg"></i></button>
                                    </div>
                                    <div class="p-4 text-sm font-mono text-slate-300 overflow-x-auto code-scroll" id="code-sim-req">
<span class="text-pink-400">curl</span> <span class="text-slate-400">-X</span> <span class="text-blue-400">GET</span> <span class="text-amber-300">"https://api.linksfield.cloud/api/v1/sim?msisdn=628111811844"</span> \
     <span class="text-slate-400">-H</span> <span class="text-amber-300">"X-Access-Key: YOUR_ACCESS_KEY"</span> \
     <span class="text-slate-400">-H</span> <span class="text-amber-300">"X-Secret-Key: YOUR_SECRET_KEY"</span>
                                    </div>
                                </div>

                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-6 mb-3">Query Parameters</h4>
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-200 dark:border-slate-700">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs font-bold text-primary">msisdn</span>
                                            <span class="text-[10px] bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 rounded font-bold uppercase">Optional</span>
                                        </div>
                                        <span class="text-xs font-mono text-slate-400">String</span>
                                    </div>
                                    <div class="pt-3 text-sm text-slate-600 dark:text-slate-400">Filter data by a specific SIM number. If omitted, returns an array of all available SIMs.</div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2"><i class="ph ph-check-circle"></i> Success Response</h4>
                                
                                <div class="rounded-xl overflow-hidden bg-[#0F172A] border border-slate-800 shadow-inner h-full max-h-[400px] flex flex-col">
                                    <div class="flex items-center justify-between px-4 py-2 bg-[#1E293B] border-b border-slate-800">
                                        <span class="text-[10px] font-mono text-emerald-400 uppercase tracking-widest flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div> 200 OK</span>
                                        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">JSON</span>
                                        <button onclick="copyCode('code-sim-res')" class="text-slate-400 hover:text-white transition-colors" title="Copy code"><i class="ph ph-copy text-lg"></i></button>
                                    </div>
                                    <div class="p-4 text-sm font-mono text-slate-300 overflow-y-auto code-scroll flex-1 whitespace-pre" id="code-sim-res">
{
  <span class="text-blue-400">"status"</span>: <span class="text-emerald-400">"success"</span>,
  <span class="text-blue-400">"total_data"</span>: <span class="text-purple-400">1</span>,
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"customer"</span>: <span class="text-amber-300">"PT Linksfield Networks Indonesia"</span>,
    <span class="text-blue-400">"msisdn"</span>: <span class="text-amber-300">"628111811844"</span>,
    <span class="text-blue-400">"iccid"</span>: <span class="text-amber-300">"896289871867621055"</span>,
    <span class="text-blue-400">"imsi"</span>: <span class="text-amber-300">"456178219341"</span>,
    <span class="text-blue-400">"sn"</span>: <span class="text-amber-300">"00013010883871844"</span>,
    <span class="text-blue-400">"data_package"</span>: {
      <span class="text-blue-400">"raw_bytes"</span>: <span class="text-purple-400">1048576000</span>,
      <span class="text-blue-400">"formatted"</span>: <span class="text-amber-300">"1,000.00 MB"</span>
    },
    <span class="text-blue-400">"usage"</span>: {
      <span class="text-blue-400">"raw_bytes"</span>: <span class="text-purple-400">104857600</span>,
      <span class="text-blue-400">"formatted"</span>: <span class="text-amber-300">"100.00 MB"</span>
    }
  }
}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        // Fungsi untuk menyalin isi code block ke clipboard
        function copyCode(elementId) {
            const codeElement = document.getElementById(elementId);
            // Hapus tag HTML agar teks murni yang tersalin
            const textToCopy = codeElement.innerText || codeElement.textContent;
            
            navigator.clipboard.writeText(textToCopy).then(() => {
                // Tampilkan Toast
                const toast = document.getElementById('copyToast');
                toast.classList.remove('translate-x-full', 'opacity-0');
                
                // Sembunyikan setelah 3 detik
                setTimeout(() => {
                    toast.classList.add('translate-x-full', 'opacity-0');
                }, 3000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>