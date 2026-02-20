<?php
// Mendapatkan nama file saat ini tanpa ekstensi
$current_page = basename($_SERVER['PHP_SELF'], ".php"); 

// Style
$active_link_style = "bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-sm ring-1 ring-indigo-200 dark:ring-transparent font-semibold";
$inactive_link_style = "text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-indigo-600 dark:hover:text-white font-medium";

function getIconClass($page_name, $current_page, $icon_name) {
    $fill = ($current_page == $page_name) ? 'ph-fill' : '';
    return "ph {$fill} {$icon_name} text-xl";
}
?>

<aside id="sidebar" class="group fixed left-0 top-0 z-50 flex h-screen w-[280px] flex-col overflow-y-hidden bg-white dark:bg-[#24303F] duration-300 ease-in-out lg:static lg:translate-x-0 -translate-x-full border-r border-slate-100 dark:border-slate-800 font-['Inter']">
    
    <div class="flex items-center justify-between gap-2 px-6 pt-10 pb-6 lg:pt-12 lg:pb-8">
        <a href="dashboard" class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/20">
                <i class="ph ph-lightning text-2xl"></i>
            </div>
            <span class="text-xl font-bold text-slate-800 dark:text-white opacity-100 duration-300 group-[.is-collapsed]:opacity-0">
                Usage Monitor
            </span>
        </a>
        <button id="sidebar-toggle" class="block lg:hidden text-slate-500 hover:text-indigo-600">
            <i class="ph ph-arrow-left text-2xl"></i>
        </button>
    </div>

    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
        <nav class="mt-2 px-4 lg:mt-4 lg:px-6 pb-10">
            
            <div>
                <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">MENU</h3>
                <ul class="flex flex-col gap-2">
                    
                    <?php if(hasPermission('dashboard')): ?>
                    <li>
                        <a href="dashboard" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'dashboard') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('dashboard', $current_page, 'ph-squares-four'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasPermission('sim_list')): ?>
                    <li>
                        <a href="sim-list" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sim-list' || $current_page == 'sim-detail') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('sim-list', $current_page, 'ph-sim-card'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM Monitor</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasPermission('sim_upload')): ?>
                    <li>
                        <a href="sim-upload" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'sim-upload') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('sim-upload', $current_page, 'ph-upload-simple'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SIM Upload</span>
                        </a>
                    </li>
                    <?php endif; ?>

                </ul>
            </div>

            <?php 
            // Cek akses untuk group Administration (Update: include manage_company & settings)
            $hasAdminAccess = hasPermission('manage_users') || hasPermission('manage_roles') || hasPermission('manage_company') || hasPermission('settings');
            
            if($hasAdminAccess): 
            ?>
            <div class="mt-8">
                <h3 class="mb-4 ml-4 text-xs font-bold text-slate-400 uppercase tracking-wider group-[.is-collapsed]:hidden">ADMINISTRATION</h3>
                <ul class="flex flex-col gap-2">
                    
                    <?php if(hasPermission('manage_users')): ?>
                    <li>
                        <a href="manage-users" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-users') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-users', $current_page, 'ph-users'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">User Management</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasPermission('manage_roles')): ?>
                    <li>
                        <a href="manage-roles" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-roles') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-roles', $current_page, 'ph-lock-key'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Role Management</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if(hasPermission('manage_company')): ?>
                    <li>
                        <a href="manage-company" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-company') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-company', $current_page, 'ph-buildings'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">Company Management</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(hasPermission('settings')): ?>
                    <li>
                        <a href="manage-smtp" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-smtp') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-smtp', $current_page, 'ph-envelope-simple'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">SMTP Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage-api" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo ($current_page == 'manage-api') ? $active_link_style : $inactive_link_style; ?>">
                            <i class="<?php echo getIconClass('manage-api', $current_page, 'ph-key'); ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">API Management</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="api-docs" class="relative flex items-center gap-2.5 rounded-xl px-4 py-3 transition-all group-[.is-collapsed]:justify-center <?php echo (basename($_SERVER['PHP_SELF']) == 'api-docs.php' || current(explode('.', basename($_SERVER['REQUEST_URI']))) == 'api-docs') ? 'bg-indigo-50 dark:bg-indigo-900/30 text-primary font-bold' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'; ?>">
                            <i class="ph ph-book-open-text text-xl <?php echo (basename($_SERVER['PHP_SELF']) == 'api-docs.php' || current(explode('.', basename($_SERVER['REQUEST_URI']))) == 'api-docs') ? 'text-primary' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300'; ?>"></i>
                            <span class="group-[.is-collapsed]:hidden whitespace-nowrap">API Documentation</span>
                        </a>
                    </li>

                </ul>
            </div>
            <?php endif; ?>

        </nav>
    </div>
</aside>