<?php 
include 'config.php';
checkLogin();

// --- PERMISSION & SCOPE LOGIC (BAWAAN v1 - DIPERTAHANKAN) ---
$current_user_id = $_SESSION['user_id'];
$current_role    = $_SESSION['role'];
$is_admin        = in_array($current_role, ['superadmin', 'admin']);

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- HELPER: HIERARCHY BUILDER (BAWAAN v1 - DIPERTAHANKAN) ---
function buildUserCompanyTree(array $elements, $parentId = 0) {
    $branch = array();
    foreach ($elements as $element) {
        $pid = $element['parent_id'] ?? 0;
        if ($pid == $parentId || ($parentId == 0 && !isset($elements[$pid]))) {
            $children = buildUserCompanyTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

function flatUserCompanyTree($tree, $depth = 0) {
    $result = [];
    foreach ($tree as $node) {
        $node['_depth'] = $depth;
        $children = $node['children'] ?? [];
        unset($node['children']);
        $result[] = $node;
        if (!empty($children)) {
            $result = array_merge($result, flatUserCompanyTree($children, $depth + 1));
        }
    }
    return $result;
}

// --- 1. GET COMPANIES (BAWAAN v1 - DIPERTAHANKAN) ---
$raw_companies = [];
$my_min_level = 99; 

if ($is_admin) {
    $q = "SELECT id, company_name, level, parent_id FROM companies ORDER BY company_name ASC";
} else {
    $uCheck = $conn->query("SELECT is_global FROM users WHERE id = $current_user_id")->fetch_assoc();
    if ($uCheck && $uCheck['is_global']) {
        $q = "SELECT id, company_name, level, parent_id FROM companies ORDER BY company_name ASC";
    } else {
        $q = "SELECT c.id, c.company_name, c.level, c.parent_id 
              FROM companies c 
              JOIN user_company_access uca ON c.id = uca.company_id 
              WHERE uca.user_id = $current_user_id
              ORDER BY c.company_name ASC";
    }
}

$res = $conn->query($q);
if($res) {
    while($r = $res->fetch_assoc()) {
        $raw_companies[$r['id']] = $r;
        if ($r['level'] < $my_min_level) $my_min_level = $r['level'];
    }
}

// Build Tree
$companies_indexed = $raw_companies;
$tree = [];
foreach ($companies_indexed as $id => $node) {
    $pid = $node['parent_id'];
    if (empty($pid) || !isset($companies_indexed[$pid])) {
        $node['children'] = buildUserCompanyTree($companies_indexed, $id);
        $tree[] = $node;
    }
}
$my_companies_sorted = flatUserCompanyTree($tree);

// --- HELPER: PASSWORD, USER CODE & EMAIL ---
function generateRandomPassword($length = 10) {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%"), 0, $length);
}

// Helper baru untuk membuat User Code (Format: LFID-xxxx)
function generateUserCode() {
    return 'LFID-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
}

function getModernEmailBody($title, $subtitle, $contentBlocks, $actionBtn = null) {
    $year = date('Y');
    $baseUrl = "https://usage.linksfield.id"; 
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f4f5; margin: 0; padding: 0; }
            .email-container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .header { background-color: #4F46E5; padding: 30px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 0.5px; }
            .content { padding: 40px 30px; color: #334155; line-height: 1.6; }
            .greeting { font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 20px; }
            .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 25px 0; }
            .info-row { margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 10px; }
            .info-row:last-child { margin-bottom: 0; border-bottom: none; padding-bottom: 0; }
            .label { font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
            .value { font-size: 16px; color: #0f172a; font-weight: 500; font-family: Consolas, monospace; }
            .btn-container { text-align: center; margin-top: 30px; }
            .btn { background-color: #4F46E5; color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2); }
            .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
            .footer a { color: #4F46E5; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'><h1>IoT Platform</h1></div>
            <div class='content'>
                <div class='greeting'>$title</div>
                <p>$subtitle</p>
                <div class='info-box'>$contentBlocks</div>
                <p style='font-size: 13px; color: #64748b;'>For security reasons, please change your password immediately after your first login.</p>
                " . ($actionBtn ? "<div class='btn-container'><a href='$baseUrl' class='btn'>$actionBtn</a></div>" : "") . "
            </div>
            <div class='footer'><p>&copy; $year PT Linksfield Networks Indonesia. All rights reserved.<br><a href='$baseUrl'>$baseUrl</a></p></div>
        </div>
    </body>
    </html>";
}

function sendSystemEmail($to, $subject, $htmlBody) {
    $cfg = getSmtpConfig();
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host']; $mail->SMTPAuth = true;
        $mail->Username = $cfg['username']; $mail->Password = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption']; $mail->Port = $cfg['port'];
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->send(); return true;
    } catch (Exception $e) { return false; }
}

$msg = ''; $msg_type = '';

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // 1. ADD / EDIT USER
    if (isset($_POST['save_user'])) {
        $id = $_POST['user_id'] ?? null;
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $target_role = $_POST['role'];
        $selected_companies = $_POST['company_ids'] ?? [];
        $is_global = isset($_POST['is_global']) ? 1 : 0;

        // Validation based on Scope
        if (!$is_admin && $target_role != 'user') {
            $msg = "Access Denied: You can only create Standard Users."; $msg_type = "error";
        } 
        elseif (!$is_admin && !empty(array_diff($selected_companies, array_keys($raw_companies)))) {
            $msg = "Access Denied: You cannot assign companies you do not manage."; $msg_type = "error";
        }
        else {
            if (empty($id)) {
                // INSERT
                $chk = $conn->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
                if ($chk->num_rows > 0) { $msg = "Username or Email already exists."; $msg_type = "error"; } 
                else {
                    $plain_pass = generateRandomPassword();
                    $hash_pass = password_hash($plain_pass, PASSWORD_DEFAULT);
                    $user_code = generateUserCode(); // Generate Code Baru LFID-xxxx
                    
                    if (!$is_admin) $is_global = 0;

                    // Tambahkan user_code ke query insert
                    $stmt = $conn->prepare("INSERT INTO users (user_code, username, email, password, phone, role, is_global, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("ssssssi", $user_code, $username, $email, $hash_pass, $phone, $target_role, $is_global);
                    
                    if ($stmt->execute()) {
                        $new_uid = $conn->insert_id;
                        if (!empty($selected_companies)) {
                            $vals = []; foreach($selected_companies as $cid) $vals[] = "($new_uid, ".intval($cid).")";
                            if(!empty($vals)) {
                                $conn->query("INSERT INTO user_company_access (user_id, company_id) VALUES " . implode(',', $vals));
                            }
                        }
                        
                        // Email Logic
                        $contentBlocks = "<div class='info-row'><span class='label'>User Code</span><div class='value'>$user_code</div></div>
                                          <div class='info-row'><span class='label'>Username</span><div class='value'>$username</div></div>
                                          <div class='info-row'><span class='label'>Email</span><div class='value'>$email</div></div>
                                          <div class='info-row'><span class='label'>Password</span><div class='value'>$plain_pass</div></div>
                                          <div class='info-row'><span class='label'>Role</span><div class='value'>".ucfirst($target_role)."</div></div>";
                        $emailBody = getModernEmailBody("Welcome Aboard!", "Your account has been successfully created. Here are your access details:", $contentBlocks, "Login to Dashboard");
                        
                        if (sendSystemEmail($email, 'Your Account Credentials', $emailBody)) {
                            $msg = "User created & credentials emailed!"; $msg_type = "success";
                        } else {
                            $msg = "User created but failed to send email."; $msg_type = "warning";
                        }
                    } else { $msg = "DB Error: " . $conn->error; $msg_type = "error"; }
                }
            } else {
                // UPDATE
                $can_update = $is_admin;
                if (!$is_admin) {
                    $can_update = true; 
                }

                if ($can_update) {
                    if (!$is_admin) $is_global = 0;
                    
                    $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, is_global=? WHERE id=?");
                    $stmt->bind_param("ssssii", $username, $email, $phone, $target_role, $is_global, $id);
                    if ($stmt->execute()) {
                        $conn->query("DELETE FROM user_company_access WHERE user_id=$id");
                        if (!empty($selected_companies)) {
                            $vals = []; foreach($selected_companies as $cid) $vals[] = "($id, ".intval($cid).")";
                            if(!empty($vals)) {
                                $conn->query("INSERT INTO user_company_access (user_id, company_id) VALUES " . implode(',', $vals));
                            }
                        }
                        $msg = "User updated successfully."; $msg_type = "success";
                    }
                }
            }
        }
    }

    // 2. DELETE USER
    if (isset($_POST['delete_user'])) {
        $id = $_POST['delete_id'];
        if ($id == $_SESSION['user_id']) { 
            $msg = "You cannot delete yourself!"; $msg_type = "error"; 
        } else {
            $conn->query("DELETE FROM user_company_access WHERE user_id=$id");
            $conn->query("DELETE FROM users WHERE id=$id");
            $msg = "User deleted successfully."; $msg_type = "success";
        }
    }

    // 3. RESET PASSWORD
    if (isset($_POST['reset_password'])) {
        $id = $_POST['reset_id'];
        $email = $_POST['reset_email'];
        $new_pass = generateRandomPassword(10);
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed' WHERE id=$id");
        
        $contentBlocks = "<div class='info-row'><span class='label'>Account Email</span><div class='value'>$email</div></div>
                          <div class='info-row'><span class='label'>New Password</span><div class='value'>$new_pass</div></div>";
        $emailBody = getModernEmailBody("Password Reset", "Your password has been reset by the administrator. Use the new password below to login:", $contentBlocks, "Login Now");
        
        if (sendSystemEmail($email, 'Security Notice - Password Reset', $emailBody)) {
            $msg = "Password reset & sent to $email."; $msg_type = "success";
        } else {
            $msg = "Password reset but Email failed."; $msg_type = "warning";
        }
    }
}

// --- FETCH USERS ---
$sql = "SELECT u.*, GROUP_CONCAT(c.id) as assigned_ids, GROUP_CONCAT(c.company_name SEPARATOR ', ') as assigned_names 
        FROM users u 
        LEFT JOIN user_company_access uca ON u.id = uca.user_id 
        LEFT JOIN companies c ON uca.company_id = c.id ";

$where = [];

if (!$is_admin) {
    $my_comp_ids_str = implode(',', array_keys($raw_companies));
    if (empty($my_comp_ids_str)) {
        $where[] = "u.id = $current_user_id";
    } else {
        $where[] = "(u.id = $current_user_id OR u.id IN (
            SELECT DISTINCT user_id FROM user_company_access WHERE company_id IN ($my_comp_ids_str)
        ))";
        $where[] = "u.role NOT IN ('superadmin', 'admin')";
    }
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
$users = $conn->query($sql);
$total_users = $users->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
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
                    colors: { primary: '#4F46E5', darkcard: '#1E293B', darkbg: '#0F172A' },
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
                <h4 class="font-bold text-slate-800 dark:text-white text-sm">Notification</h4>
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
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 animate-fade-in-up">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg text-primary">
                            <i class="ph ph-users text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">User Accounts</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Manage access and permissions.</p>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64 group">
                            <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors"></i>
                            <input type="text" id="searchInput" onkeyup="filterUsers()" placeholder="Search user..." class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-darkcard border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:text-white shadow-sm transition-all">
                        </div>
                        
                        <?php if($my_min_level < 4 || $is_admin): ?>
                        <button onclick="openModal()" class="bg-primary hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 shadow-lg shadow-indigo-500/20 active:scale-95 transition-all group">
                            <i class="ph ph-user-plus text-lg group-hover:scale-110 transition-transform"></i> 
                            <span>Add User</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($msg): ?>
                <div class="mb-6 animate-fade-in-up p-4 rounded-xl border flex items-center gap-3 <?= $msg_type=='success'?'bg-emerald-50 border-emerald-200 text-emerald-700':'bg-red-50 border-red-200 text-red-700' ?>">
                    <i class="ph <?= $msg_type=='success'?'ph-check-circle':'ph-warning-circle' ?> text-xl"></i>
                    <span class="text-sm font-medium"><?= $msg ?></span>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-darkcard rounded-2xl shadow-lg border border-slate-100 dark:border-slate-800 overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s;">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" id="userTable">
                            <thead class="bg-slate-50 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 font-bold uppercase text-[11px] tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-6 py-4">User</th>
                                    <th class="px-6 py-4">User Code</th> <th class="px-6 py-4">Role</th>
                                    <th class="px-6 py-4">Access Scope</th>
                                    <th class="px-6 py-4">Contact</th>
                                    <th class="px-6 py-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php if($total_users > 0): while($u = $users->fetch_assoc()): 
                                    $initial = strtoupper(substr($u['username'], 0, 2));
                                    $roleBadge = match($u['role']) {
                                        'superadmin' => 'bg-purple-50 text-purple-700 border-purple-100',
                                        'admin' => 'bg-blue-50 text-blue-700 border-blue-100',
                                        default => 'bg-slate-50 text-slate-600 border-slate-100'
                                    };
                                    
                                    // Secure JSON encode untuk data edit
                                    $editData = htmlspecialchars(json_encode([
                                        'id' => $u['id'],
                                        'username' => $u['username'],
                                        'email' => $u['email'],
                                        'phone' => $u['phone'],
                                        'role' => $u['role'],
                                        'is_global' => $u['is_global'],
                                        'assigned_ids' => $u['assigned_ids']
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors user-row group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-indigo-50 text-primary flex items-center justify-center font-bold text-xs ring-1 ring-indigo-100"><?= $initial ?></div>
                                            <div>
                                                <p class="font-bold text-slate-900 dark:text-white user-name"><?= htmlspecialchars($u['username']) ?></p>
                                                <p class="text-xs text-slate-400 user-email"><?= htmlspecialchars($u['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <?php if(!empty($u['user_code'])): ?>
                                            <span class="inline-block px-2.5 py-1 rounded-lg text-[11px] font-mono font-bold bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700"><?= $u['user_code'] ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold border <?= $roleBadge ?>"><?= ucfirst($u['role']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($u['is_global']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-100">
                                                <i class="ph ph-globe"></i> GLOBAL ACCESS
                                            </span>
                                        <?php elseif($u['assigned_names']): ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php 
                                                $comps = explode(', ', $u['assigned_names']);
                                                $max_show = 2;
                                                foreach(array_slice($comps, 0, $max_show) as $cname) {
                                                    echo '<span class="px-2 py-0.5 text-[10px] bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded text-slate-600 dark:text-slate-300">'.htmlspecialchars($cname).'</span>';
                                                }
                                                if(count($comps) > $max_show) echo '<span class="px-2 py-0.5 text-[10px] text-slate-400">+'.(count($comps)-$max_show).'</span>';
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">No specific access</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-slate-500"><?= $u['phone'] ?: '-' ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button type="button" onclick="editUser(<?= $editData ?>)" class="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all"><i class="ph ph-pencil-simple text-lg"></i></button>
                                            
                                            <button type="button" onclick="confirmReset('<?= $u['id'] ?>', '<?= addslashes($u['email']) ?>')" class="p-2 rounded-lg text-slate-400 hover:text-amber-500 hover:bg-amber-50 transition-all"><i class="ph ph-key text-lg"></i></button>
                                            
                                            <?php if($u['id'] != $current_user_id): ?>
                                            <button type="button" onclick="confirmDelete('<?= $u['id'] ?>', '<?= addslashes($u['username']) ?>')" class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-all"><i class="ph ph-trash text-lg"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="6" class="p-8 text-center text-slate-400 italic">No users found in your scope.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <div id="userModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-darkcard text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 scale-95" id="modalPanel">
                    <form method="POST" onsubmit="showProcessing(this)">
                        <input type="hidden" name="save_user" value="1">
                        <input type="hidden" name="user_id" id="modal_id">
                        
                        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modalTitle">Add New User</h3>
                            <p class="text-xs text-slate-500 mt-1">Credentials will be emailed. LFID Code generated automatically.</p>
                        </div>
                        
                        <div class="p-6 space-y-5">
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Username <span class="text-red-500">*</span></label>
                                    <input type="text" name="username" id="modal_username" required class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Role <span class="text-red-500">*</span></label>
                                    <select name="role" id="modal_role" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                        <option value="user">User (Client)</option>
                                        <?php if($is_admin): ?>
                                        <option value="admin">Admin</option>
                                        <option value="superadmin">Superadmin</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" id="modal_email" required class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Phone</label>
                                    <input type="text" name="phone" id="modal_phone" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2.5 bg-white dark:bg-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-xs font-bold text-slate-500 uppercase">Access Scope</label>
                                </div>
                                
                                <?php if($is_admin): ?>
                                <div class="flex items-center gap-3 p-3 bg-fuchsia-50 dark:bg-fuchsia-900/10 border border-fuchsia-100 dark:border-fuchsia-800/50 rounded-xl mb-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_global" id="is_global" class="sr-only peer" onchange="toggleCompanyList()">
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-fuchsia-600"></div>
                                    </label>
                                    <div>
                                        <span class="block text-sm font-bold text-slate-800 dark:text-white">Global Access</span>
                                        <span class="block text-[10px] text-slate-500">Can view ALL companies automatically.</span>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div id="companyListContainer" class="transition-all duration-300">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Select Companies</span>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input type="checkbox" id="selectAllCompanies" class="w-3.5 h-3.5 rounded text-primary focus:ring-primary border-slate-300 dark:border-slate-500 bg-white dark:bg-slate-700">
                                            <span class="text-[10px] font-bold text-primary">All</span>
                                        </label>
                                    </div>
                                    <div class="max-h-52 overflow-y-auto border border-slate-200 dark:border-slate-600 rounded-xl p-1 bg-white dark:bg-slate-800 scrollbar-thin">
                                        <?php if(empty($my_companies_sorted)): ?>
                                            <div class="p-3 text-center text-xs text-slate-400">No companies available to assign.</div>
                                        <?php else: foreach($my_companies_sorted as $c): 
                                            // Indent Visual
                                            $indent = $c['_depth'] * 20;
                                            $treeIcon = ($c['_depth'] > 0) ? '<i class="ph ph-arrow-elbow-down-right text-slate-300 mr-2"></i>' : '';
                                            
                                            // Level Badge
                                            $lvl = $c['level'] ?? 'N/A';
                                            $lvlBadge = match($lvl) {
                                                '1' => 'bg-blue-50 text-blue-700 border-blue-100',
                                                '2' => 'bg-purple-50 text-purple-700 border-purple-100',
                                                '3' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                '4' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                                default => 'bg-slate-50 text-slate-600 border-slate-100'
                                            };
                                        ?>
                                        <label class="flex items-center gap-3 p-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg cursor-pointer transition-colors border-b border-slate-50 dark:border-slate-700/50 last:border-0 group">
                                            <input type="checkbox" name="company_ids[]" value="<?= $c['id'] ?>" class="comp-check w-4 h-4 rounded text-primary focus:ring-primary border-slate-300 dark:border-slate-500 bg-slate-50 dark:bg-slate-700 flex-shrink-0">
                                            <div class="flex-1 flex justify-between items-center overflow-hidden">
                                                <div class="flex items-center truncate" style="padding-left: <?= $indent ?>px">
                                                    <?= $treeIcon ?>
                                                    <span class="text-sm text-slate-700 dark:text-slate-300 font-medium group-hover:text-primary transition-colors truncate"><?= htmlspecialchars($c['company_name']) ?></span>
                                                </div>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border flex-shrink-0 <?= $lvlBadge ?>">L<?= $lvl ?></span>
                                            </div>
                                        </label>
                                        <?php endforeach; endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-700 rounded-b-2xl">
                            <button type="button" onclick="closeModal('userModal')" class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-white border border-transparent hover:border-slate-200 transition-all">Cancel</button>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-indigo-600 shadow-lg active:scale-95 flex items-center gap-2">
                                <span>Save User</span> <i class="ph ph-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeModal('deleteModal')"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center">
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-darkcard text-left shadow-2xl w-full max-w-sm p-6 scale-95 opacity-0 transition-all" id="deletePanel">
                    <div class="text-center">
                        <div class="bg-red-100 dark:bg-red-900/30 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600 animate-pulse"><i class="ph ph-trash text-3xl"></i></div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Delete User?</h3>
                        <p class="text-sm text-slate-500 mb-6">You are about to delete <strong id="delUserName" class="text-slate-800 dark:text-white"></strong>. This action is irreversible.</p>
                        <form method="POST" class="flex justify-center gap-3">
                            <input type="hidden" name="delete_user" value="1">
                            <input type="hidden" name="delete_id" id="deleteId">
                            <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2.5 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 shadow-lg shadow-red-500/30 transition-all active:scale-95">Yes, Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="resetModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeModal('resetModal')"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center">
                <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-darkcard text-left shadow-2xl w-full max-w-sm p-6 scale-95 opacity-0 transition-all" id="resetPanel">
                    <div class="text-center">
                        <div class="bg-amber-100 dark:bg-amber-900/30 w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i class="ph ph-key text-3xl"></i></div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Reset Password?</h3>
                        <p class="text-sm text-slate-500 mb-6">A new password will be generated and emailed to <strong id="resetEmailText" class="text-slate-800 dark:text-white"></strong>.</p>
                        <form method="POST" class="flex justify-center gap-3" onsubmit="showProcessing(this)">
                            <input type="hidden" name="reset_password" value="1">
                            <input type="hidden" name="reset_id" id="resetId">
                            <input type="hidden" name="reset_email" id="resetEmail">
                            <button type="button" onclick="closeModal('resetModal')" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                            <button type="submit" class="px-4 py-2.5 rounded-xl bg-amber-500 text-white font-bold hover:bg-amber-600 shadow-lg shadow-amber-500/30 transition-all active:scale-95">Confirm Reset</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // --- ANIMASI MODAL ---
        function animateModal(modalId, show) {
            const modal = document.getElementById(modalId);
            const backdrop = modal.querySelector('div[id$="Backdrop"]') || modal.querySelector('.backdrop-blur-sm');
            let panel = modal.querySelector('div[id$="Panel"]');

            if(show) {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    if(backdrop) backdrop.classList.remove('opacity-0');
                    if(panel) {
                        panel.classList.remove('opacity-0', 'scale-95');
                        panel.classList.add('opacity-100', 'scale-100');
                    }
                }, 10);
            } else {
                if(backdrop) backdrop.classList.add('opacity-0');
                if(panel) {
                    panel.classList.remove('opacity-100', 'scale-100');
                    panel.classList.add('opacity-0', 'scale-95');
                }
                setTimeout(() => modal.classList.add('hidden'), 300);
            }
        }

        function openModal() {
            document.getElementById('modalTitle').innerText = 'Add New User';
            document.getElementById('modal_id').value = '';
            document.querySelector('#userModal form').reset();
            const globalSwitch = document.getElementById('is_global');
            if(globalSwitch) { globalSwitch.checked = false; toggleCompanyList(); }
            document.querySelectorAll('.comp-check').forEach(c => c.checked = false);
            animateModal('userModal', true);
        }

        function editUser(data) {
            document.getElementById('modalTitle').innerText = 'Edit User';
            document.getElementById('modal_id').value = data.id;
            document.getElementById('modal_username').value = data.username;
            document.getElementById('modal_email').value = data.email;
            document.getElementById('modal_phone').value = data.phone;
            document.getElementById('modal_role').value = data.role;
            const globalSwitch = document.getElementById('is_global');
            if(globalSwitch) { globalSwitch.checked = (data.is_global == 1); toggleCompanyList(); }
            
            const assigned = data.assigned_ids ? data.assigned_ids.split(',') : [];
            const checkboxes = document.querySelectorAll('.comp-check');
            let allChecked = true;
            checkboxes.forEach(cb => {
                const isChecked = assigned.includes(cb.value);
                cb.checked = isChecked;
                if(!isChecked) allChecked = false;
            });
            if(checkboxes.length > 0 && document.getElementById('selectAllCompanies')) {
                document.getElementById('selectAllCompanies').checked = allChecked;
            }
            animateModal('userModal', true);
        }

        function toggleCompanyList() {
            const isGlobal = document.getElementById('is_global');
            if(!isGlobal) return;
            const container = document.getElementById('companyListContainer');
            const checkboxes = document.querySelectorAll('.comp-check');
            const selectAll = document.getElementById('selectAllCompanies');
            if(isGlobal.checked) {
                container.classList.add('opacity-40', 'pointer-events-none');
                checkboxes.forEach(cb => cb.disabled = true);
                if(selectAll) selectAll.disabled = true;
            } else {
                container.classList.remove('opacity-40', 'pointer-events-none');
                checkboxes.forEach(cb => cb.disabled = false);
                if(selectAll) selectAll.disabled = false;
            }
        }

        function closeModal(id) { animateModal(id, false); }

        function confirmDelete(id, name) { 
            document.getElementById('deleteId').value = id; 
            document.getElementById('delUserName').innerText = name; 
            animateModal('deleteModal', true); 
        }

        function confirmReset(id, email) { 
            document.getElementById('resetId').value = id; 
            document.getElementById('resetEmail').value = email; 
            document.getElementById('resetEmailText').innerText = email; 
            animateModal('resetModal', true); 
        }

        function showProcessing(form) { 
            const btn = form.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Processing...';
            btn.disabled = true;
        }
        
        function hideToast() { document.getElementById('toast').classList.add('translate-x-full', 'opacity-0'); }
        
        <?php if($msg): ?>
        setTimeout(() => {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').innerHTML = "<?= $msg ?>";
            t.classList.remove('translate-x-full', 'opacity-0');
            setTimeout(hideToast, 4000);
        }, 100);
        <?php endif; ?>

        const selectAll = document.getElementById('selectAllCompanies');
        if(selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.comp-check').forEach(cb => cb.checked = this.checked);
            });
            document.querySelectorAll('.comp-check').forEach(cb => {
                cb.addEventListener('change', function() {
                    const all = document.querySelectorAll('.comp-check');
                    const checked = document.querySelectorAll('.comp-check:checked');
                    selectAll.checked = (all.length === checked.length);
                });
            });
        }

        function filterUsers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const rows = document.getElementById('userTable').getElementsByClassName('user-row');
            for (let i = 0; i < rows.length; i++) {
                const name = rows[i].querySelector('.user-name').textContent || "";
                const email = rows[i].querySelector('.user-email').textContent || "";
                rows[i].style.display = (name.toLowerCase().indexOf(filter) > -1 || email.toLowerCase().indexOf(filter) > -1) ? "" : "none";
            }
        }
    </script>
</body>
</html>