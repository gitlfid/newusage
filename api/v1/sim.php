<?php
// Set Header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Access-Key, X-Secret-Key, Content-Type');

// Load Config
require_once '../../config.php';

// Helper Format Bytes
function formatBytesAPI($bytes) { 
    if ($bytes <= 0) return '0 MB';
    $mb = $bytes / 1048576; 
    return number_format($mb, 2) . ' MB';
}

// 1. Dapatkan Header Auth
$headers = getallheaders();
$access_key = $headers['X-Access-Key'] ?? ($_SERVER['HTTP_X_ACCESS_KEY'] ?? '');
$secret_key = $headers['X-Secret-Key'] ?? ($_SERVER['HTTP_X_SECRET_KEY'] ?? '');

// 2. Validasi Kredensial API
if (empty($access_key) || empty($secret_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Missing API Keys']);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, status FROM api_keys WHERE access_key = ? AND secret_key = ?");
$stmt->bind_param("ss", $access_key, $secret_key);
$stmt->execute();
$auth_result = $stmt->get_result();

if ($auth_result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid API Keys']);
    exit;
}

$api_user = $auth_result->fetch_assoc();
if ($api_user['status'] == 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: API Key is inactive']);
    exit;
}

// 3. Proses Parameter (MSISDN - Opsional)
$msisdn = $_GET['msisdn'] ?? '';

// 4. CEK AKSES KETAT (STRICT SCOPE ACCESS)
$user_id = $api_user['user_id'];

// Ambil info role dan is_global dari pemilik API key
$uQ = $conn->prepare("SELECT role, is_global FROM users WHERE id = ?");
$uQ->bind_param("i", $user_id);
$uQ->execute();
$uData = $uQ->get_result()->fetch_assoc();

$company_condition = "";

// LOGIKA: Jika BUKAN Superadmin dan BUKAN Global Access, maka filter secara ketat!
if ($uData['role'] !== 'superadmin' && $uData['is_global'] != 1) {
    // Cari perusahaan apa saja yang di-assign ke user ini
    $accessQ = $conn->prepare("SELECT company_id FROM user_company_access WHERE user_id = ?");
    $accessQ->bind_param("i", $user_id);
    $accessQ->execute();
    $accessRes = $accessQ->get_result();
    
    $allowed_ids = [];
    while($row = $accessRes->fetch_assoc()) {
        $allowed_ids[] = $row['company_id'];
    }
    
    if (empty($allowed_ids)) {
        // Jika tidak di-assign ke customer manapun, block akses
        $company_condition = " AND 1=0 "; 
    } else {
        // Gabungkan array menjadi string, contoh: "1, 2, 5"
        $ids_str = implode(',', $allowed_ids);
        $company_condition = " AND sims.company_id IN ($ids_str) ";
    }
}

// 5. Query Data SIM Dinamis (Satu atau Semua)
$sql = "SELECT sims.msisdn, sims.iccid, sims.imsi, sims.sn, sims.total_flow, sims.used_flow, companies.company_name 
        FROM sims 
        LEFT JOIN companies ON sims.company_id = companies.id
        WHERE 1=1 $company_condition";

// Jika request 1 MSISDN spesifik
if (!empty($msisdn)) {
    $sql .= " AND sims.msisdn = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $msisdn);
} else {
    // Jika tidak ada MSISDN, tampilkan semua sesuai hak akses
    $sql .= " ORDER BY sims.id DESC";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

// 6. Siapkan JSON Response
if ($result->num_rows > 0) {
    $data_array = [];
    
    while($sim = $result->fetch_assoc()) {
        $data_array[] = [
            'customer' => $sim['company_name'] ?? 'Unknown',
            'msisdn' => $sim['msisdn'],
            'iccid' => $sim['iccid'],
            'imsi' => $sim['imsi'],
            'sn' => $sim['sn'],
            'data_package' => [
                'raw_bytes' => (float)$sim['total_flow'],
                'formatted' => formatBytesAPI($sim['total_flow'])
            ],
            'usage' => [
                'raw_bytes' => (float)$sim['used_flow'],
                'formatted' => formatBytesAPI($sim['used_flow'])
            ]
        ];
    }
    
    // Kembalikan Object jika cari 1 nomor, Kembalikan Array jika cari semua nomor
    $response_data = (!empty($msisdn)) ? $data_array[0] : $data_array;

    $response = [
        'status' => 'success',
        'total_data' => count($data_array),
        'data' => $response_data
    ];
    
    http_response_code(200);
    echo json_encode($response);
} else {
    // Not found (Bisa karena nomor tidak ada, ATAU nomor ada tapi punya customer lain)
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'No SIM Cards found or access denied due to your permission scope']);
}
?>