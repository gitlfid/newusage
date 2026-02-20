<?php
// Pastikan script bisa berjalan lama tanpa timeout
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config-api.php'; // File fungsi API yang dibuat sebelumnya

echo "Starting Indosat API Sync...\n";

// Ambil semua nomor indosat dari database
$sql = "SELECT id, msisdn, total_flow, rollover_flow, used_flow FROM sims WHERE msisdn LIKE '62815%' OR msisdn LIKE '62816%' OR msisdn LIKE '62856%' OR msisdn LIKE '62857%'";
$res = $conn->query($sql);

if ($res->num_rows === 0) {
    die("No Indosat numbers found in database.\n");
}

$token = getIndosatToken();
if (!$token) {
    die("Error: Failed to retrieve API Token from Indosat.\n");
}

$stmt = $conn->prepare("UPDATE sims SET total_flow=?, rollover_flow=?, used_flow=? WHERE id=?");
$success = 0; 
$fail = 0;

while ($row = $res->fetch_assoc()) {
    $msisdn = $row['msisdn'];
    $id = $row['id'];
    
    // Default value ambil dari database agar jika API gagal, data tidak menjadi 0
    $totalRaw = floatval($row['total_flow']);
    $rolloverRaw = floatval($row['rollover_flow']);
    $usedRaw = floatval($row['used_flow']);

    $benefit = getIndosatBenefit($token, $msisdn);
    $traffic = getIndosatTraffic($token, $msisdn);

    // Proses Kuota & Rollover
    if (isset($benefit['services'][0]['quotas'])) {
        foreach ($benefit['services'][0]['quotas'] as $quota) {
            if (strtoupper($quota['type']) === 'DATA' || stripos($quota['name'], 'utama') !== false) {
                $valRemaining = floatval($quota['remaining'] ?? 0);
                $unit = strtoupper($quota['unit'] ?? 'GB');
                $totalRaw = ($unit === 'MB') ? $valRemaining * 1048576 : ($valRemaining * 1000) * 1048576;
            }
            if (strtoupper($quota['type']) === 'DATAROLLOVER' || stripos($quota['name'], 'rollover') !== false) {
                $valRemaining = floatval($quota['remaining'] ?? 0);
                $unit = strtoupper($quota['unit'] ?? 'GB');
                $rolloverRaw = ($unit === 'MB') ? $valRemaining * 1048576 : ($valRemaining * 1000) * 1048576;
            }
        }
    }

    // Proses Pemakaian (Usage)
    if (isset($traffic['traffic']['data'][0])) {
        $valUsage = floatval($traffic['traffic']['data'][0]['value'] ?? 0);
        $unit = strtoupper($traffic['traffic']['data'][0]['unit'] ?? 'GB');
        if ($unit === 'MB') $usedRaw = $valUsage * 1048576;
        elseif ($unit === 'KB') $usedRaw = ($valUsage / 1000) * 1048576;
        else $usedRaw = ($valUsage * 1000) * 1048576;
    }

    // Eksekusi Update ke Database
    $stmt->bind_param("dddi", $totalRaw, $rolloverRaw, $usedRaw, $id);
    if ($stmt->execute()) {
        $success++;
    } else {
        $fail++;
    }

    // Jeda kecil agar tidak terkena limit request dari server Indosat
    usleep(100000); // 0.1 Detik
}

echo "Sync Completed! Success: $success, Failed: $fail\n";
?>