<?php
// Set Headers JSON & CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load Config
require_once '../../config.php';

// 1. BACA PARAMETER INPUT (JSON Body)
$input = json_decode(file_get_contents('php://input'), true);
$user_code = $input['user_code'] ?? '';

if (empty($user_code)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: Parameter user_code is required']);
    exit;
}

// 2. CARI USER BERDASARKAN USER_CODE
$stmt_user = $conn->prepare("SELECT id, username FROM users WHERE user_code = ?");
$stmt_user->bind_param("s", $user_code);
$stmt_user->execute();
$user_res = $stmt_user->get_result();

if ($user_res->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User with the specified user_code not found']);
    exit;
}

$target_user = $user_res->fetch_assoc();
$target_user_id = $target_user['id'];
$target_username = $target_user['username'];

// 3. CEK APAKAH USER SUDAH PUNYA KEY AKTIF
// Mencegah generate berulang kali agar tidak ditumpuk spam
$check_key = $conn->query("SELECT id FROM api_keys WHERE user_id = $target_user_id AND status = 1");
if ($check_key->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['status' => 'error', 'message' => 'This user already has an active API Key. Please revoke it first via Dashboard.']);
    exit;
}

// 4. GENERATE KEY BARU
function generateRandomStringAPI($length) {
    return bin2hex(random_bytes($length / 2));
}

$new_access_key = generateRandomStringAPI(32);
$new_secret_key = generateRandomStringAPI(64);
$app_name = "API Key - " . $target_username . " (Auto via Self-Service API)";

// 5. SIMPAN KE DATABASE & TAMPILKAN RESPONSE
$stmt_insert = $conn->prepare("INSERT INTO api_keys (user_id, app_name, access_key, secret_key) VALUES (?, ?, ?, ?)");
$stmt_insert->bind_param("isss", $target_user_id, $app_name, $new_access_key, $new_secret_key);

if ($stmt_insert->execute()) {
    http_response_code(201); // Created
    echo json_encode([
        'status' => 'success',
        'message' => 'API Keys generated successfully',
        'data' => [
            'user_code'  => $user_code,
            'username'   => $target_username,
            'access_key' => $new_access_key,
            'secret_key' => $new_secret_key
        ]
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>