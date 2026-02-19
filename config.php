<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

$db_host = "localhost";
$db_user = "lfid_newusage";
$db_pass = "Kumisna5";
$db_name = "lfid_newusage"; // Sesuaikan

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper: Cek Login
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login");
        exit();
    }
}

// Helper: Cek Akses Halaman (Role Based)
function hasAccess($page_role_required) {
    // $page_role_required bisa berupa array role yang diizinkan
    // Contoh: hasAccess(['superadmin', 'admin'])
    $user_role = $_SESSION['role'] ?? '';
    
    if($user_role === 'superadmin') return true;
    if(in_array($user_role, $page_role_required)) return true;
    
    return false;
}

// Helper: Ambil ID Company yang boleh diakses user (UPDATED UNTUK API)
function getClientIdsForUser($user_id) {
    global $conn;
    
    // Cek apakah dipanggil via Web (Session) atau via API (Stateless)
    $role = '';
    if (isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
    } else {
        // Jika tidak ada session (dipanggil via API), ambil role dari database
        $stmt_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt_role->bind_param("i", $user_id);
        $stmt_role->execute();
        $res_role = $stmt_role->get_result();
        if ($row = $res_role->fetch_assoc()) {
            $role = $row['role'];
        }
    }

    // Superadmin & Admin melihat SEMUA data (biasanya)
    // Jika Admin hanya boleh lihat client tertentu, ubah logika ini
    if ($role == 'superadmin' || $role == 'admin') {
        return 'ALL'; 
    }

    // User Client: Ambil dari tabel user_company_access
    $ids = [];
    $stmt = $conn->prepare("SELECT company_id FROM user_company_access WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while($row = $res->fetch_assoc()) {
        $ids[] = $row['company_id'];
    }

    if(empty($ids)) return 'NONE';
    return $ids;
}

// Helper: Get SMTP Config
function getSmtpConfig() {
    global $conn;
    $q = $conn->query("SELECT * FROM smtp_settings WHERE id = 1");
    return $q->fetch_assoc();
}

// --- config.php (Tambahkan di bagian bawah) ---

function hasPermission($menu_key) {
    global $conn;
    
    // 1. Cek Login
    if (!isset($_SESSION['role'])) return false;
    $role = $_SESSION['role'];

    // 2. Superadmin selalu TRUE (Bypass DB)
    if ($role === 'superadmin') return true;

    // 3. Cek Database
    // Disarankan menggunakan SESSION Cache agar tidak query DB setiap saat
    // Tapi untuk live update, query langsung oke untuk skala kecil/menengah
    $stmt = $conn->prepare("SELECT id FROM role_permissions WHERE role = ? AND menu_key = ?");
    $stmt->bind_param("ss", $role, $menu_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// --- SECURITY HELPER: Enforce Page Access ---
function enforcePermission($menu_key) {
    global $conn;

    // 1. Pastikan user sudah login
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }

    $role = $_SESSION['role'];

    // 2. Superadmin selalu lolos (Bypass)
    if ($role === 'superadmin') {
        return true;
    }

    // 3. Cek Database: Apakah Role ini punya akses ke Menu ini?
    // Kita gunakan query langsung agar realtime (langsung efek saat diubah)
    $stmt = $conn->prepare("SELECT id FROM role_permissions WHERE role = ? AND menu_key = ?");
    $stmt->bind_param("ss", $role, $menu_key);
    $stmt->execute();
    $result = $stmt->get_result();

    // 4. Jika tidak ada izin, tendang ke Dashboard
    if ($result->num_rows === 0) {
        // Opsional: Set pesan error di session untuk ditampilkan di dashboard
        // $_SESSION['error_msg'] = "Access Denied: You do not have permission to access that page.";
        header("Location: dashboard.php"); 
        exit();
    }
}

?>