<?php
require_once __DIR__ . '/config.php';

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function upbit_jwt_token($accessKey, $secretKey, array $payload = []) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = array_merge(['access_key' => $accessKey, 'nonce' => uniqid('', true)], $payload);

    $jwtHeader = base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $jwtPayload = base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $jwtHeader . '.' . $jwtPayload, $secretKey, true);
    return $jwtHeader . '.' . $jwtPayload . '.' . base64url_encode($signature);
}

function http_get_json($url, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'HTTP GET failed');
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP $status: $res");
    }
    $json = json_decode($res, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON response');
    }
    return $json;
}

function http_get_raw($url, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err ?: 'HTTP GET failed');
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP $status: $res");
    }
    return $res;
}

function fetch_upbit_krw_balance(): float {
    $token = upbit_jwt_token(UPBIT_ACCESS_KEY, UPBIT_SECRET_KEY);
    $headers = ["Authorization: Bearer {$token}"];
    $url = 'https://api.upbit.com/v1/accounts';
    $accounts = http_get_json($url, $headers);
    foreach ($accounts as $acc) {
        if (isset($acc['currency']) && strtoupper($acc['currency']) === 'KRW') {
            $balance = isset($acc['balance']) ? (float)$acc['balance'] : 0.0;
            $locked = isset($acc['locked']) ? (float)$acc['locked'] : 0.0;
            return $balance + $locked;
        }
    }
    return 0.0;
}

function fetch_binance_usdt_balance(): float {
    $base = 'https://api.binance.com';
    $timestamp = (int) floor(microtime(true) * 1000);
    $qs = http_build_query(['timestamp' => $timestamp, 'recvWindow' => 5000]);
    $sig = hash_hmac('sha256', $qs, BINANCE_API_SECRET);
    $url = $base . '/api/v3/account?' . $qs . '&signature=' . $sig;
    $headers = ['X-MBX-APIKEY: ' . BINANCE_API_KEY];

    $raw = http_get_raw($url, $headers);
    $data = json_decode($raw, true);
    if (!isset($data['balances']) || !is_array($data['balances'])) {
        return 0.0;
    }
    foreach ($data['balances'] as $bal) {
        if (isset($bal['asset']) && strtoupper($bal['asset']) === 'USDT') {
            $free = isset($bal['free']) ? (float)$bal['free'] : 0.0;
            $locked = isset($bal['locked']) ? (float)$bal['locked'] : 0.0;
            return $free + $locked;
        }
    }
    return 0.0;
}
