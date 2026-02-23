<?php
// Pastikan script bisa berjalan tanpa batas waktu dan batas memory yang cukup
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/config.php';

echo "Starting Indosat API Sync...\n";

// Fungsi get token khusus cron agar selalu fresh per batch (menghindari token expired saat proses lama)
function getFreshIndosatToken() {
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://myim3biz.indosatooredoo.com/api/m2m/auth/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10, 
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode([
          "grant_type" => "client_credentials",
          "client_id" => "c81d8a14-b199-493f-b127-ae2903bb0803",
          "client_secret" => "3QhmWbxYQCUaePfoIYcXgjAXaykJMjeq5YriFOtA"
      ]),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Accept: application/json'
      ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    
    return $data['access_token'] ?? false;
}

// Fungsi get data API
function getIndosatData($token, $endpoint, $payload) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://myim3biz.indosatooredoo.com/api/m2m/ido-mobile/' . $endpoint,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
      ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

// Pengaturan Batch Processing (Anti-Limit & Memory Safe)
$batchSize = 100;
$offset = 0;
$success = 0; 
$fail = 0;
$currentMonth = date('Y-m'); // Penanda bulan berjalan (contoh: 2026-02)

$stmt = $conn->prepare("UPDATE sims SET total_flow=?, rollover_flow=?, used_flow=?, max_rollover=?, rollover_period=? WHERE id=?");

while (true) {
    // Ambil data menggunakan LIMIT & OFFSET (Hanya panggil 100 baris ke memori)
    $sql = "SELECT id, msisdn, total_flow, rollover_flow, used_flow, max_rollover, rollover_period 
            FROM sims 
            WHERE msisdn LIKE '62815%' OR msisdn LIKE '62816%' OR msisdn LIKE '62856%' OR msisdn LIKE '62857%' 
            ORDER BY id ASC 
            LIMIT $batchSize OFFSET $offset";
    
    $res = $conn->query($sql);
    
    // Jika data sudah habis, hentikan looping
    if ($res->num_rows === 0) {
        break;
    }

    // Ambil Token baru setiap ganti batch agar token tidak expired di tengah jalan
    $token = getFreshIndosatToken();
    if (!$token) {
        echo "Failed to get API token at offset $offset. Retrying next time...\n";
        $fail += $res->num_rows;
        $offset += $batchSize;
        sleep(5);
        continue;
    }

    $batchNum = ($offset / $batchSize) + 1;
    echo "Processing Batch {$batchNum} (Offset: {$offset})...\n";

    while ($row = $res->fetch_assoc()) {
        $msisdn = $row['msisdn'];
        $id = $row['id'];
        
        // Ambil data lama dari DB sebagai nilai default (jaga-jaga jika API error, data tidak jadi 0)
        $totalRaw = floatval($row['total_flow']);
        $rolloverRaw = floatval($row['rollover_flow']);
        $usedRaw = floatval($row['used_flow']);
        $dbMaxRollover = floatval($row['max_rollover']);
        $dbRolloverPeriod = $row['rollover_period'];

        // Tembak API
        $benefit = getIndosatData($token, 'remaining-benefit', ["asset" => $msisdn]);
        $traffic = getIndosatData($token, 'traffic-summary', [
            "group" => "ASSET",
            "period" => "MONTHLY",
            "range" => [$currentMonth, $currentMonth],
            "asset" => $msisdn
        ]);

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

        // LOGIKA PENCATATAN NILAI TERTINGGI (MAX ROLLOVER)
        if ($dbRolloverPeriod !== $currentMonth) {
            // Jika berganti bulan (misal dari Jan ke Feb), Reset nilai menjadi Rollover yang baru didapat
            $newMaxRollover = $rolloverRaw;
        } else {
            // Jika masih di bulan yang sama, simpan nilai yang paling tinggi
            $newMaxRollover = max($dbMaxRollover, $rolloverRaw);
        }

        // Simpan ke database
        $stmt->bind_param("ddddsi", $totalRaw, $rolloverRaw, $usedRaw, $newMaxRollover, $currentMonth, $id);
        if ($stmt->execute()) {
            $success++;
        } else {
            $fail++;
        }

        // Pause 0.2 Detik per data agar API tidak melakukan blokir atas aktivitas mencurigakan
        usleep(200000); 
    }

    // Lanjut ke kelompok 100 data berikutnya
    $offset += $batchSize;
    
    // Istirahatkan server selama 3 detik sebelum melanjutkan ke batch berikutnya
    sleep(3); 
}

echo "Sync Completed! Successfully updated: $success, Failed: $fail\n";
?>