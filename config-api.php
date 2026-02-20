<?php
/**
 * =========================================================
 * PUSAT KONFIGURASI DAN FUNGSI API (INDOSAT, TELKOMSEL, DLL)
 * =========================================================
 */

// ==========================================
// 1. API INDOSAT OOREDOO
// ==========================================

function getIndosatToken() {
    static $token = null;
    if ($token !== null) return $token;

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://myim3biz.indosatooredoo.com/api/m2m/auth/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 5, // Timeout agar tidak hang jika server indosat lambat
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
    
    if (isset($data['access_token'])) {
        $token = $data['access_token'];
        return $token;
    }
    return false;
}

function getIndosatBenefit($token, $msisdn) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://myim3biz.indosatooredoo.com/api/m2m/ido-mobile/remaining-benefit',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 5,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_POSTFIELDS => json_encode(["asset" => $msisdn]),
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

function getIndosatTraffic($token, $msisdn) {
    $currentMonth = date('Y-m');
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://myim3biz.indosatooredoo.com/api/m2m/ido-mobile/traffic-summary',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 5,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_POSTFIELDS => json_encode([
          "group" => "ASSET",
          "period" => "MONTHLY",
          "range" => [$currentMonth, $currentMonth], // Dinamis mengikuti bulan berjalan
          "asset" => $msisdn
      ]),
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


// ==========================================
// 2. API TELKOMSEL (Disiapkan untuk nanti)
// ==========================================

/*
function getTelkomselToken() {
    // Logika API Telkomsel nanti ditaruh disini...
}

function getTelkomselUsage($token, $msisdn) {
    // Logika API Telkomsel nanti ditaruh disini...
}
*/

?>